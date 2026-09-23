#!/usr/bin/env python3
"""
    graphify_collapse.py
    ------------------------------------------------------------------
    Post-build transform for the graphify knowledge graph.

    graphify extracts PHP with an AST that emits NO edge for `new Foo()`.
    The effect is that every controller -> model/service instantiation, and
    the whole plan-type family behind PlanTypeRegistry, is invisible. That
    silently distorts any centrality/betweenness ranking (PlanTypeRegistry
    swings from rank 37 to rank 1 once repaired -- see the project notes).

    This script:

      1. Scans PHP source for `new X(` sites and reports the ones the graph
         is missing -- rewriting them as `instantiates` edges (the repair).
      2. Collapses method nodes into their owning classes and single-class
         file nodes into that class, dropping `method`/`contains` bookkeeping
         spokes that otherwise inflate degree.
      3. Restricts to code class nodes, recomputes degree / betweenness /
         articulation points / communities, and prints a ranking.

    Every reconstructed edge is flagged in the output and counted in the
    report, and the ranking shows each node's rank WITHOUT the repair as
    well -- so the dependency on reconstructed data is never hidden.

    Requires: networkx, and graphify's own interpreter (which has it).
    No Composer, no extra install.

    Usage (from project root, after a /graphify run):
        "$(cat graphify-out/.graphify_python)" scripts/graphify_collapse.py
        "$(cat graphify-out/.graphify_python)" scripts/graphify_collapse.py --no-repair
        "$(cat graphify-out/.graphify_python)" scripts/graphify_collapse.py --top 25

    Windows (PowerShell):
        & (Get-Content graphify-out/.graphify_python) scripts/graphify_collapse.py

    Output (gitignored):
        graphify-out/graph-collapsed.json              repaired class-level graph
        graphify-out/missing_instantiation_edges.json  the repair, for review
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from collections import defaultdict
from pathlib import Path

try:
    import networkx as nx
except ImportError:  # pragma: no cover
    sys.exit("networkx is required. Run this with graphify's interpreter:\n"
             '  "$(cat graphify-out/.graphify_python)" scripts/graphify_collapse.py')

DEFAULT_ROOTS = ("app", "routes", "public", "config")
RE_CLASS = re.compile(r"^\s*(?:final\s+|abstract\s+)?class\s+(\w+)", re.M)
RE_NEW = re.compile(r"\bnew\s+([A-Z]\w*)\s*\(")
BOOKKEEPING = ("method", "contains")


def norm_segment(seg: str) -> str:
    """graphify's node-id normalisation: lowercase, non-alphanumerics -> _"""
    return re.sub(r"[^a-z0-9]", "_", seg.lower())


def node_stem(path: str | Path) -> str:
    """repo-relative path with the extension dropped, segments joined by _"""
    parts = Path(path).with_suffix("").parts
    return "_".join(norm_segment(p) for p in parts)


def pair(a: str, b: str) -> tuple[str, str]:
    return (a, b) if a <= b else (b, a)


def load_graph(path: Path) -> dict:
    if not path.exists():
        sys.exit(f"No graph at {path}\nRun /graphify first to create it.")
    data = json.loads(path.read_text(encoding="utf-8"))
    if "links" not in data and "edges" in data:      # tolerate other producers
        data["links"] = data["edges"]
    if "nodes" not in data or "links" not in data:
        sys.exit(f"{path} does not look like a graphify graph (need 'nodes' and 'links').")
    return data


def build_owner_map(graph: dict) -> tuple[dict, dict]:
    """method node -> owning class, plus unambiguous single-class file -> class."""
    nodes = {n["id"]: n for n in graph["nodes"]}
    owner: dict[str, str] = {}
    for e in graph["links"]:
        if e.get("relation") == "method":
            owner[e["target"]] = e["source"]
    contained: dict[str, list[str]] = defaultdict(list)
    for e in graph["links"]:
        if e.get("relation") == "contains":
            contained[e["source"]].append(e["target"])
    for f, cs in contained.items():
        if len(cs) == 1 and nodes.get(cs[0], {}).get("_callable_class"):
            owner[f] = cs[0]
    return owner, nodes


def make_resolver(owner: dict):
    def resolve(nid: str) -> str:
        first = owner.get(nid, nid)          # method -> class
        return owner.get(first, first)       # file  -> class (if it was a file)
    return resolve


def raw_adjacency(graph: dict) -> set:
    """Existing non-bookkeeping links, at raw node-id granularity.

    Deliberately NOT resolved to classes: `find_missing` asks "does the graph
    know about this `new` at all?", and an answer at class granularity would
    hide a genuine missing instantiation behind an unrelated method-call edge
    between the same two classes.
    """
    adj = set()
    for e in graph["links"]:
        if e.get("relation") in BOOKKEEPING:
            continue
        if e["source"] != e["target"]:
            adj.add(pair(e["source"], e["target"]))
    return adj


def scan_new_sites(root: Path, roots: tuple[str, ...]):
    """Every `new X(` in PHP source, with its enclosing class (may be None)."""
    files: list[Path] = []
    for r in roots:
        d = root / r
        if d.is_dir():
            files += list(d.rglob("*.php"))
    files = sorted(set(files))

    defines: dict[str, str] = {}
    for f in files:
        for m in RE_CLASS.finditer(f.read_text(encoding="utf-8", errors="ignore")):
            defines.setdefault(m.group(1), f.relative_to(root).as_posix())

    sites: list[dict] = []
    for f in files:
        rel = f.relative_to(root).as_posix()
        current = None
        for lineno, line in enumerate(f.read_text(encoding="utf-8", errors="ignore").splitlines(), 1):
            cm = RE_CLASS.match(line)
            if cm:
                current = cm.group(1)
            for m in RE_NEW.finditer(line):
                sites.append({"file": rel, "line": lineno,
                              "source_class": current, "target_class": m.group(1)})
    return sites, defines


def find_missing(sites, defines, node_ids: set, adj: set) -> tuple[list[dict], dict]:
    """`new` sites present in source with no corresponding edge in the graph."""
    missing, counts = [], defaultdict(int)
    for s in sites:
        src, tgt = s["source_class"], s["target_class"]
        if src is None:
            counts["no_enclosing_class"] += 1
            continue
        if tgt not in defines:
            counts["target_not_in_repo"] += 1     # PDO, RuntimeException, TCPDF, CDN Chart...
            continue
        sid = f"{node_stem(s['file'])}_{norm_segment(src)}"
        tid = f"{node_stem(defines[tgt])}_{norm_segment(tgt)}"
        if sid == tid:
            counts["self_instantiation"] += 1
            continue
        if sid not in node_ids or tid not in node_ids:
            counts["node_not_in_graph"] += 1
            continue
        if pair(sid, tid) in adj:
            counts["already_linked"] += 1         # graph already knows this one
            continue
        missing.append({**s, "source_id": sid, "target_id": tid})
    return missing, counts


def build_collapsed(graph: dict, owner: dict, nodes: dict, resolve, extra: list[dict]) -> nx.Graph:
    """Class-only graph: methods and single-class files folded into their classes."""
    def kept(nid: str) -> bool:
        n = nodes.get(nid)
        return bool(n) and n.get("file_type") == "code" and n.get("_callable_class")

    G = nx.Graph()
    for nid in nodes:
        cid = resolve(nid)
        if cid not in G and kept(cid):
            G.add_node(cid)

    seen: set = set()

    def add(s: str, t: str, relation: str, reconstructed: bool) -> None:
        s, t = resolve(s), resolve(t)
        if s == t or s not in G or t not in G or pair(s, t) in seen:
            return
        seen.add(pair(s, t))
        G.add_edge(s, t, relation=relation, reconstructed=reconstructed)

    for e in graph["links"]:
        if e.get("relation") in BOOKKEEPING:
            continue
        add(e["source"], e["target"], e.get("relation"), False)
    for m in extra:
        add(m["source_id"], m["target_id"], "instantiates", True)
    return G


def rank(G: nx.Graph) -> tuple[dict, dict, set]:
    bc = nx.betweenness_centrality(G)
    order = {nid: i for i, (nid, _) in enumerate(sorted(bc.items(), key=lambda kv: -kv[1]), 1)}
    return bc, order, set(nx.articulation_points(G))


def main() -> int:
    ap = argparse.ArgumentParser(
        description="Collapse the graphify graph to class level and repair missing `new` edges.")
    ap.add_argument("--root", default=None, help="project root (default: parent of scripts/)")
    ap.add_argument("--graph", default="graphify-out/graph.json", help="input graph")
    ap.add_argument("--out", default="graphify-out/graph-collapsed.json", help="output graph")
    ap.add_argument("--repair-file", default="graphify-out/missing_instantiation_edges.json")
    ap.add_argument("--no-repair", action="store_true", help="skip the instantiation-edge repair")
    ap.add_argument("--top", type=int, default=15, help="ranking rows to print")
    ap.add_argument("--source-roots", nargs="*", default=list(DEFAULT_ROOTS))
    args = ap.parse_args()

    root = Path(args.root).resolve() if args.root else Path(__file__).resolve().parent.parent
    graph = load_graph(root / args.graph)
    owner, nodes = build_owner_map(graph)
    resolve = make_resolver(owner)
    node_ids = {n["id"] for n in graph["nodes"]}

    print("QMS :: graphify post-build collapse")
    print(f"Root  : {root}")
    print(f"Graph : {args.graph}  ({len(graph['nodes'])} nodes, {len(graph['links'])} links)")

    # ---- 1. repair -------------------------------------------------------
    adj = raw_adjacency(graph)
    sites, defines = scan_new_sites(root, tuple(args.source_roots))
    missing, skip = find_missing(sites, defines, node_ids, adj)

    print(f"\nScanned {len(sites)} `new` sites across {', '.join(args.source_roots)}")
    if skip:
        print("  " + " | ".join(f"{v} {k.replace('_', ' ')}" for k, v in sorted(skip.items())))
    if args.no_repair:
        missing_used = []
        print("  repair DISABLED (--no-repair)")
    else:
        missing_used = missing
        print(f"  MISSING instantiation edges: {len(missing)} "
              f"-> added as `instantiates` (flagged reconstructed=true)")

    repair_path = root / args.repair_file
    repair_path.parent.mkdir(parents=True, exist_ok=True)
    repair_path.write_text(json.dumps(missing, indent=2, ensure_ascii=False), encoding="utf-8")

    # ---- 2/3. collapse + rank, with and without the repair ---------------
    G = build_collapsed(graph, owner, nodes, resolve, missing_used)
    if G.number_of_nodes() == 0:
        sys.exit("Collapsed graph is empty - nothing to rank.")
    bc, order, arts = rank(G)
    # rank the un-repaired graph too, so every row can show its pre-repair rank
    G_pre = build_collapsed(graph, owner, nodes, resolve, [])
    _, order_pre, _ = rank(G_pre)

    labels = {i: n.get("label", i) for i, n in nodes.items()}
    comm = {i: n.get("community_name") for i, n in nodes.items()}
    folded = sum(1 for nid in nodes if resolve(nid) != nid)
    # Of the missing sites, only some change the class-level topology - the rest
    # were already implied by a method-call edge between the same two classes.
    added_edges = G.number_of_edges() - G_pre.number_of_edges()

    print(f"\nCollapsed: {G.number_of_nodes()} classes, {G.number_of_edges()} edges, "
          f"{nx.number_connected_components(G)} component(s), "
          f"{sum(1 for n in G if G.degree(n) <= 1)} node(s) with degree<=1, "
          f"{len(arts)} articulation point(s)")
    print(f"  ({folded} method/file nodes folded into their classes)")
    if not args.no_repair:
        already = len(missing) - added_edges
        print(f"  repair added {added_edges} class-level edge(s); "
              f"{already} of the {len(missing)} were already implied by other links")

    print(f"\n=== betweenness ranking (top {args.top}) ===")
    print(f"{'#':<4}{'btw':>8}{'deg':>5}{'artic':>7}{'pre':>6}  {'class':<26}community")
    for nid, v in sorted(bc.items(), key=lambda kv: -kv[1])[: args.top]:
        pre = order_pre.get(nid)
        print(f"{order[nid]:<4}{v:>8.4f}{G.degree(nid):>5}"
              f"{'yes' if nid in arts else '':>7}{(f'#{pre}' if pre else ''):>6}  "
              f"{labels.get(nid, nid)[:26]:<26}{comm.get(nid) or ''}")

    print("\n=== communities ===")
    for c in sorted(nx.community.greedy_modularity_communities(G), key=len, reverse=True):
        print(f"  n={len(c):<3} {', '.join(sorted(labels.get(m, m) for m in c))}")

    # ---- write -----------------------------------------------------------
    payload = {
        "generated_by": "scripts/graphify_collapse.py",
        "source_graph": args.graph,
        "repair_applied": not args.no_repair,
        "reconstructed_edges": sum(1 for _, _, d in G.edges(data=True) if d.get("reconstructed")),
        "counts": {"nodes": G.number_of_nodes(), "edges": G.number_of_edges(),
                   "components": nx.number_connected_components(G), "folded": folded,
                   "missing_instantiation_sites": len(missing),
                   "class_level_edges_added": added_edges},
        "nodes": [{"id": i, "label": labels.get(i, i), "community_name": comm.get(i),
                   "degree": G.degree(i), "betweenness": round(bc[i], 6), "rank": order[i],
                   "rank_without_repair": order_pre.get(i), "articulation": i in arts}
                  for i in G.nodes],
        "links": [{"source": s, "target": t, "relation": d.get("relation"),
                   "reconstructed": d.get("reconstructed", False)}
                  for s, t, d in G.edges(data=True)],
    }
    out_path = root / args.out
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")

    print(f"\nWrote {args.out}  ({payload['reconstructed_edges']} of "
          f"{G.number_of_edges()} edges are reconstructed)")
    print(f"Wrote {args.repair_file}  (review before trusting a rank that depends on it)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
