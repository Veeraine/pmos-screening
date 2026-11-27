<?php

/*
|--------------------------------------------------------------------------
| Symptom detection evaluation notes
|--------------------------------------------------------------------------
|
| Realistic doctor's notes, written in many different ways, each with the
| symptom codes a doctor would expect to be suggested. Used by
| "php artisan pmos:evaluate-detection" and by SymptomDetectionEvaluationTest
| to measure how well symptom detection works.
|
| Symptom codes are the keys of "symptom_keywords" in config/screening.php.
| A note with no expected symptoms ([]) should get no suggestions at all.
|
| Add new notes here (for example, real wording seen in clinic) to keep
| measuring detection as the phrase lists change.
|
*/

return [

    // Everyday wording and number patterns
    ['note' => 'Periods every 2-3 months since menarche. Pimples on forehead.', 'expected' => ['irregular_periods', 'acne']],
    ['note' => 'Periods are erratic and unpredictable.', 'expected' => ['irregular_periods']],
    ['note' => 'Missed her periods for 4 months, pregnancy test negative.', 'expected' => ['absent_periods']],
    ['note' => 'Menses last 2 weeks, soaking pads.', 'expected' => ['prolonged_bleeding']],
    ['note' => 'Losing hair at the crown, scalp visible.', 'expected' => ['hair_thinning']],
    ['note' => 'Excessive facial and body hair, shaves weekly.', 'expected' => ['excess_hair_growth']],
    ['note' => 'Spots on face and back, oily skin.', 'expected' => ['acne']],
    ['note' => 'Has gained 10 kg in 6 months, feels tired.', 'expected' => ['weight_gain']],
    ['note' => 'Dark velvety patches on neck and armpits.', 'expected' => ['dark_skin_patches']],
    ['note' => 'Trying for a baby for 2 years without success. Cycles 40-60 days.', 'expected' => ['difficulty_conceiving', 'irregular_periods']],
    ['note' => 'Moustache and chin hair, plucks daily. No acne.', 'expected' => ['excess_hair_growth']],
    ['note' => 'Thinning of scalp hair, receding hair line.', 'expected' => ['hair_thinning']],
    ['note' => 'Unable to conceive since marriage 4 yrs ago.', 'expected' => ['difficulty_conceiving']],
    ['note' => 'Irregular periods, cycle length varies 35-90 days.', 'expected' => ['irregular_periods']],
    ['note' => 'Weight increased from 65 to 80 kg. Dark patches on the neck.', 'expected' => ['weight_gain', 'dark_skin_patches']],
    ['note' => 'Obese, BMI 36, struggling to get pregnant, oligo-ovulation on tracking.', 'expected' => ['difficulty_conceiving', 'irregular_periods']],

    // Medical terms and abbreviations
    ['note' => 'LMP 5 months ago, UPT negative.', 'expected' => ['absent_periods']],
    ['note' => 'C/O hirsutism and acne vulgaris. BMI 32.', 'expected' => ['excess_hair_growth', 'acne']],
    ['note' => 'Amenorrhoea x 6/12. Wt gain 8kg over past year.', 'expected' => ['absent_periods', 'weight_gain']],
    ['note' => 'USS: bilateral PCO morphology, >12 follicles per ovary.', 'expected' => ['polycystic_ovaries_on_ultrasound']],
    ['note' => 'Primary infertility x3 yrs. Oligomenorrhea.', 'expected' => ['difficulty_conceiving', 'irregular_periods']],
    ['note' => 'HMB, periods lasting 10 days each cycle.', 'expected' => ['prolonged_bleeding']],
    ['note' => 'Female pattern hair loss. Acanthosis nigricans on neck.', 'expected' => ['hair_thinning', 'dark_skin_patches']],
    ['note' => 'Irregular cycles and hirsute, ?PCOS.', 'expected' => ['irregular_periods', 'excess_hair_growth']],
    ['note' => 'Menorrhagia and prolonged menses, Hb 8.2.', 'expected' => ['prolonged_bleeding']],
    ['note' => 'Scan: multiple peripheral follicles in both ovaries, string of pearls appearance.', 'expected' => ['polycystic_ovaries_on_ultrasound']],

    // Misspellings
    ['note' => 'Hirsutsm on chin and upper lip. Irregullar menses.', 'expected' => ['excess_hair_growth', 'irregular_periods']],
    ['note' => 'Oligomenorrhoea, hirsuitism, acne. Ultrasound shows polycystic ovaries.', 'expected' => ['irregular_periods', 'excess_hair_growth', 'acne', 'polycystic_ovaries_on_ultrasound']],
    ['note' => 'Secondary amenorhea for 8 months.', 'expected' => ['absent_periods']],
    ['note' => 'Polycistic ovaries on USS, irreguler periods.', 'expected' => ['polycystic_ovaries_on_ultrasound', 'irregular_periods']],
    ['note' => 'Acnee and facial hair.', 'expected' => ['acne', 'excess_hair_growth']],

    // Negatives before and after the symptom
    ['note' => 'Acne: none. Hirsutism - nil. Periods regular.', 'expected' => []],
    ['note' => 'No acne, no hirsutism, cycles regular.', 'expected' => []],
    ['note' => 'Pimples - nil. Facial hair - nil. Periods regular monthly.', 'expected' => []],
    ['note' => 'Denies hirsutism. Acne present on cheeks.', 'expected' => ['acne']],
    ['note' => 'Hirsutism absent, acne present.', 'expected' => ['acne']],
    ['note' => 'Acne not present. Periods normal. Here for malaria follow-up.', 'expected' => []],

    // Unrelated illnesses: nothing should be suggested
    ['note' => 'Fever 39, chills and rigors for 2 days. MRDT positive, started LA.', 'expected' => []],
    ['note' => 'Headache, BP 150/95, known hypertensive on nifedipine.', 'expected' => []],
    ['note' => 'Dysuria and frequency for 3 days. No vaginal bleeding.', 'expected' => []],
    ['note' => 'Abdominal pain RIF with nausea, appendicitis ruled out.', 'expected' => []],
    ['note' => 'Routine antenatal visit at 24 weeks, fetal movements felt.', 'expected' => []],

];
