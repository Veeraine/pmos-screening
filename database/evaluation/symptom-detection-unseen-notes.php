<?php

/*
|--------------------------------------------------------------------------
| Unseen symptom detection notes
|--------------------------------------------------------------------------
|
| A second set of doctor's notes, kept apart from symptom-detection-notes.php.
| These are NOT used when adding phrases or rules: they only measure how well
| detection works on wording it was not tuned for, which is closer to what
| happens with real notes in clinic.
|
| "php artisan pmos:evaluate-detection" shows only the totals for this set.
| Use --show-unseen to list the individual notes, but avoid changing phrase
| lists to fit them, or they stop being a fair test.
|
| Symptom codes are the keys of "symptom_keywords" in config/screening.php.
|
*/

return [

    // Written after the first round of improvements, before they were measured here
    ['note' => 'Menstrual cycle irregular since teenage years, sometimes 3 months without bleeding.', 'expected' => ['irregular_periods']],
    ['note' => 'No menses since January. Pregnancy excluded.', 'expected' => ['absent_periods']],
    ['note' => 'Complains of dark hair on her chin and around the nipples.', 'expected' => ['excess_hair_growth']],
    ['note' => 'Bad acne on the jawline, using benzoyl peroxide.', 'expected' => ['acne']],
    ['note' => 'Scalp hair is getting thin at the front.', 'expected' => ['hair_thinning']],
    ['note' => 'Married 5 years, no child yet, both partners investigated.', 'expected' => ['difficulty_conceiving']],
    ['note' => 'TVS: both ovaries enlarged with 15+ small follicles arranged peripherally.', 'expected' => ['polycystic_ovaries_on_ultrasound']],
    ['note' => 'Weight up 12kg since last year despite diet.', 'expected' => ['weight_gain']],
    ['note' => 'Thick dark skin at the back of the neck.', 'expected' => ['dark_skin_patches']],
    ['note' => 'Periods heavy and last 9-10 days.', 'expected' => ['prolonged_bleeding']],
    ['note' => 'Oligomenorrhoea and hirsutisim noted, query PCOS.', 'expected' => ['irregular_periods', 'excess_hair_growth']],
    ['note' => 'Facial hair: nil. Acne: mild.', 'expected' => ['acne']],
    ['note' => 'Malaria 2 weeks ago, now well. Periods regular.', 'expected' => []],
    ['note' => 'Hirsutism not a feature. Regular cycles.', 'expected' => []],
    ['note' => 'Cough and fever, chest crackles, treated for pneumonia.', 'expected' => []],
    ['note' => 'Annual check, no complaints, BP normal.', 'expected' => []],

    // Written before the second round of improvements
    ['note' => 'Pt reports menses q2-4 months since age 15.', 'expected' => ['irregular_periods']],
    ['note' => 'Cycle lengths range from 38 to 70 days.', 'expected' => ['irregular_periods']],
    ['note' => 'C/o excessive hair on lower abdomen and thighs.', 'expected' => ['excess_hair_growth']],
    ['note' => 'Has had no bleeding per vagina for the past 4 months, not pregnant.', 'expected' => ['absent_periods']],
    ['note' => 'Pimples all over the face and upper back for 2 years.', 'expected' => ['acne']],
    ['note' => 'Her hair has become thinner over the last year.', 'expected' => ['hair_thinning']],
    ['note' => 'Desires pregnancy, unprotected intercourse x 18 months with no conception.', 'expected' => ['difficulty_conceiving']],
    ['note' => 'Weight 92 kg, up from 78 kg last year.', 'expected' => ['weight_gain']],
    ['note' => 'Skin around neck dark and velvety.', 'expected' => ['dark_skin_patches']],
    ['note' => 'Bleeds for about 12 days every cycle, very heavy.', 'expected' => ['prolonged_bleeding']],
    ['note' => 'Pelvic USS showed PCOM bilaterally.', 'expected' => ['polycystic_ovaries_on_ultrasound']],
    ['note' => 'Known PCOS. Here for review of metformin dose.', 'expected' => []],
    ['note' => 'Polycystic ovary syndrome diagnosed 2021, on OCP, cycles now regular.', 'expected' => []],
    ['note' => 'LMP 6 weeks ago, nausea, UPT positive.', 'expected' => []],
    ['note' => 'LMP 4/12 ago. Acne on chin.', 'expected' => ['absent_periods', 'acne']],
    ['note' => 'Hair on chin: none. Acne: few comedones on nose.', 'expected' => ['acne']],
    ['note' => 'No hair loss, no weight change, no skin changes.', 'expected' => []],
    ['note' => 'Sore throat and fever, tonsils enlarged. Amoxicillin given.', 'expected' => []],
    ['note' => 'Lower back pain after lifting, no red flags.', 'expected' => []],
    ['note' => 'Generalised itchy rash after new soap, no fever.', 'expected' => []],

];
