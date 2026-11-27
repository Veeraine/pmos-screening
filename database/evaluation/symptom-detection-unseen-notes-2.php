<?php

/*
|--------------------------------------------------------------------------
| Unseen symptom detection notes, set 2
|--------------------------------------------------------------------------
|
| A third set of doctor's notes, written before the third round of
| improvements and kept apart from the other two sets. Like set 1, these are
| NOT used when adding phrases or rules: they only measure how well
| detection copes with wording it was not tuned for.
|
| "php artisan pmos:evaluate-detection" shows only the totals for this set.
| Use --show-unseen to list the individual notes, but avoid changing phrase
| lists to fit them, or they stop being a fair test.
|
| Symptom codes are the keys of "symptom_keywords" in config/screening.php.
|
*/

return [

    ['note' => 'Wt 85kg, was 70kg in 2024, gaining steadily.', 'expected' => ['weight_gain']],
    ['note' => 'Menses come at long and unpredictable intervals.', 'expected' => ['irregular_periods']],
    ['note' => 'LMP 5/12 ago, UPT -ve.', 'expected' => ['absent_periods']],
    ['note' => 'Bleeding PV for 3/52, heavy with clots.', 'expected' => ['prolonged_bleeding']],
    ['note' => 'Hair on the upper lip and chin, she waxes every week.', 'expected' => ['excess_hair_growth']],
    ['note' => 'Acne: not a feature. Hirsutism: nil.', 'expected' => []],
    ['note' => 'Hair loss not noted, skin clear.', 'expected' => []],
    ['note' => 'Menses q3/12 since menarche.', 'expected' => ['irregular_periods']],
    ['note' => 'Trying to conceive x 2 yrs, no success.', 'expected' => ['difficulty_conceiving']],
    ['note' => 'Struggled for years to fall pregnant with her first child, now trying again for 18/12.', 'expected' => ['difficulty_conceiving']],
    ['note' => 'O/E: dark, thickened, velvety skin in both axillae.', 'expected' => ['dark_skin_patches']],
    ['note' => 'Periods, when they come, are very heavy and last over a week.', 'expected' => ['prolonged_bleeding']],
    ['note' => 'USS pelvis: ovaries bulky, each with >20 follicles in a peripheral distribution.', 'expected' => ['polycystic_ovaries_on_ultrasound']],
    ['note' => 'Scalp: diffuse thinning over the vertex.', 'expected' => ['hair_thinning']],
    ['note' => 'No hirsutism, acne or alopecia on examination.', 'expected' => []],
    ['note' => 'Denies weight gain, hair loss or irregular cycles.', 'expected' => []],
    ['note' => 'Cycles every 6-10 weeks.', 'expected' => ['irregular_periods']],
    ['note' => 'Amenorrhoea since 03/2025.', 'expected' => ['absent_periods']],
    ['note' => 'Galactorrhoea, headache, visual disturbance; prolactin raised.', 'expected' => []],
    ['note' => 'Abdominal pain and diarrhoea x 3/7, no blood.', 'expected' => []],
    ['note' => 'Wound check post C-section, healing well.', 'expected' => []],
    ['note' => 'Known asthmatic, wheeze, salbutamol given.', 'expected' => []],
    ['note' => 'Pt c/o pimples on face and chest, worse before menses.', 'expected' => ['acne']],
    ['note' => 'Hirsute ++, Ferriman-Gallwey score 18.', 'expected' => ['excess_hair_growth']],
    ['note' => 'Unable to get pregnant despite regular unprotected sex for 2 years, periods regular.', 'expected' => ['difficulty_conceiving']],

];
