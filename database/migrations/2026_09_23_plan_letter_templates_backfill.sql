SET NAMES utf8mb4;

-- Backfill the six seeded plans. Existing plans that already have text in
-- either column are left untouched, so this is safe to re-run.
-- ---------------------------------------------------------------------

-- Royal Plus + Guaranteed Plus share the interest-plan letter.
UPDATE `plans` SET
    `summary_template` = 'Based on the above quotation, the customer invests ${amount} under the Oxiaura ${plan_name} plan for a term of ${term_label}.\nThe agreed return is ${payout_amount}, payable ${payout_frequency}.\nTotal scheduled returns over the ${term_label} term: ${total_return}.',
    `terms_template` = 'The investment term is ${term_months} months from the agreed commencement date.\nThe return of ${payout_amount} shall be payable according to the agreed payment schedule.\nThe investment amount is ${amount}.\nThe investment agreement and applicable company terms shall govern the investment.\nAny applicable taxes, statutory deductions, fees, or other charges shall be handled according to the applicable agreement and regulations.\nThis quotation is subject to formal acceptance and execution of the relevant investment agreement and required customer documentation.'
WHERE `plan_type` IN ('royal_plus', 'guaranteed_plus')
  AND (`summary_template` IS NULL OR `summary_template` = '')
  AND (`terms_template` IS NULL OR `terms_template` = '');

UPDATE `plans` SET
    `summary_template` = 'Based on the above quotation, the customer invests ${amount} under the ${plan_name} for a term of ${term_label}.\nThe agreed monthly re-payment is ${monthly_repay}, payable for ${payments_count} months.\nA maturity benefit of ${maturity_benefit} is paid at the end of the term, giving a total value of ${total_value}.',
    `terms_template` = 'The investment term is ${term_months} months from the agreed commencement date.\nThe monthly re-payment of ${monthly_repay} shall be payable according to the agreed payment schedule.\nA maturity benefit of ${maturity_benefit} shall be paid at the end of the term.\nThe investment amount is ${amount}.\nAny applicable taxes, statutory deductions, fees, or other charges shall be handled according to the applicable agreement and regulations.\nThis quotation is subject to formal acceptance and execution of the relevant investment agreement and required customer documentation.'
WHERE `plan_type` = 'monthly_wealth'
  AND (`summary_template` IS NULL OR `summary_template` = '')
  AND (`terms_template` IS NULL OR `terms_template` = '');

UPDATE `plans` SET
    `summary_template` = 'Based on the above quotation, the customer contributes ${monthly_contribution} monthly for ${contribution_months} months to complete a capital of ${amount} under the ${plan_name}.\nAfter completion the plan converts to a monthly re-payment plan of ${monthly_repay}, payable for ${repay_months} months.\nA maturity benefit of ${maturity_benefit} is paid at the end of the term, giving a total value of ${total_value}.',
    `terms_template` = 'The contribution term is ${contribution_months} months from the agreed commencement date.\nThe monthly payment of ${monthly_contribution} shall be payable according to the agreed payment schedule.\nThe completed capital is ${amount}.\nUpon completion the plan converts to a ${repay_months}-month re-payment plan with a maturity benefit of ${maturity_benefit}.\nAny applicable taxes, statutory deductions, fees, or other charges shall be handled according to the applicable agreement and regulations.\nThis quotation is subject to formal acceptance and execution of the relevant investment agreement and required customer documentation.'
WHERE `plan_type` = 'supreme_plus'
  AND (`summary_template` IS NULL OR `summary_template` = '')
  AND (`terms_template` IS NULL OR `terms_template` = '');

UPDATE `plans` SET
    `summary_template` = 'Based on the above quotation, the customer invests ${amount} to plant ${crop} on ${land_extent} of land under the ${plan_name} plan.\nThe company carries out planting and maintenance on the customer''s own land.\nThe projected harvest income at maturity is ${projected_harvest}.',
    `terms_template` = 'The plantation term runs from the agreed commencement date through to harvest maturity.\nThe investment amount of ${amount} covers planting and maintenance of ${crop} on ${land_extent}.\nThe company shall plant and maintain the crop on the customer''s land for the agreed term.\nOwnership of the land and the standing trees remains with the customer.\nThe projected harvest income of ${projected_harvest} is an estimate and not a guarantee.\nThis quotation is subject to formal acceptance and execution of the relevant plantation agreement.'
WHERE `plan_type` = 'golden_crop'
  AND (`summary_template` IS NULL OR `summary_template` = '')
  AND (`terms_template` IS NULL OR `terms_template` = '');

UPDATE `plans` SET
    `summary_template` = 'Based on the above quotation, the customer purchases ${quantity} ${crop} plants at ${unit_price} per plant, for a total of ${amount}.\nThe projected harvest value of the plants at maturity is ${projected_harvest}.',
    `terms_template` = 'The total purchase price is ${amount} for ${quantity} ${crop} plants at ${unit_price} per plant.\nPlants supplied are nursery-raised and of the agreed variety.\nThe projected harvest value of ${projected_harvest} is an estimate and not a guarantee.\nPlanting and maintenance after hand-over are the customer''s responsibility unless separately agreed.\nAny applicable taxes, statutory deductions, fees, or other charges shall be handled according to the applicable agreement and regulations.\nThis quotation is subject to formal acceptance and execution of the relevant purchase agreement.'
WHERE `plan_type` = 'plant_selling'
  AND (`summary_template` IS NULL OR `summary_template` = '')
  AND (`terms_template` IS NULL OR `terms_template` = '');
