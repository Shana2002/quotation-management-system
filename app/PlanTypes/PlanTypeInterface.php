<?php

declare(strict_types=1);

namespace App\PlanTypes;

/**
 * PlanTypeInterface
 *
 * A plan type is one of OXIAURA's investment/plantation products. Each type
 * defines its own quotation inputs, default rate/price parameters, benefit
 * text, and the projection (a letter intro + a table) shown on screen and in
 * the PDF. Concrete types live in this namespace and are wired in
 * PlanTypeRegistry.
 *
 * The "projection" returned by compute() is deliberately render-agnostic:
 * a headers array + rows array of pre-formatted strings, so the show view and
 * the PDF render the same data without knowing the plan type.
 */
interface PlanTypeInterface
{
    /** Stable machine key, e.g. 'royal_plus'. */
    public function key(): string;

    /** Human label, e.g. 'Royal Plus'. */
    public function label(): string;

    /** Letter heading printed on the PDF, e.g. 'Investing for Agarwood Land'. */
    public function letterTitle(): string;

    /**
     * One-line description of the calculation, shown in the builder so the
     * operator can sanity-check the assumed formula.
     */
    public function formulaNote(): string;

    /**
     * Input field schema for the quotation builder. Field shape:
     *   ['name','label','type'(number|select|text),'required'(bool),
     *    'step'?,'options'?(value=>label),'help'?]
     * Crop-type options are derived from $params.
     *
     * @param array<string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public function inputFields(array $params): array;

    /**
     * Seed/default parameters (rates, prices, durations) for a new plan of
     * this type. Stored as JSON on the plan and editable by an admin.
     *
     * @return array<string,mixed>
     */
    public function defaultParameters(): array;

    /** Default benefits / conditions text for this plan type. */
    public function defaultBenefits(): string;

    /**
     * Validate captured inputs; return a list of human error strings.
     *
     * @param array<string,mixed> $inputs
     * @return string[]
     */
    public function validate(array $inputs): array;

    /**
     * Compute the projection from inputs + plan parameters.
     *
     * @param array<string,mixed> $inputs
     * @param array<string,mixed> $params
     * @return array{
     *     intro:string,
     *     headers:array<int,string>,
     *     rows:array<int,array<int,string>>,
     *     summary:array<string,string>,
     *     headline_amount:float
     * }
     */
    public function compute(array $inputs, array $params): array;
}
