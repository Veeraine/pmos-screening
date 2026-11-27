<?php

/*
|--------------------------------------------------------------------------
| PMOS screening rules
|--------------------------------------------------------------------------
|
| Clinical rules for screening, kept out of the code so they can change with the guidelines.
| After editing on a live server run: php artisan config:clear
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Rotterdam features
    |--------------------------------------------------------------------------
    |
    | Confirmed symptoms from all visits are grouped into these signs (Teede et al., 2023).
    | A sign counts if any of its symptoms was confirmed. Symptoms not listed here don't count.
    |
    */

    'rotterdam_features' => [

        'irregular_ovulation' => [
            'label' => 'Irregular ovulation',
            'symptoms' => ['irregular_periods', 'absent_periods', 'prolonged_bleeding'],
        ],

        'high_androgens' => [
            'label' => 'Signs of high androgen levels',
            'symptoms' => ['excess_hair_growth', 'acne', 'hair_thinning'],
        ],

        'polycystic_ovaries' => [
            'label' => 'Polycystic ovaries on ultrasound',
            'symptoms' => ['polycystic_ovaries_on_ultrasound'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Alert threshold
    |--------------------------------------------------------------------------
    |
    | How many Rotterdam signs (from any visits) are needed before an alert is raised.
    |
    */

    'alert_threshold' => 2,

    /*
    |--------------------------------------------------------------------------
    | Symptom keywords
    |--------------------------------------------------------------------------
    |
    | Words looked for in the notes to suggest symptoms. Lowercase, whole words only.
    | # = any number, {3+} / {<21} / {>35} = number rules. Don't change the keys (symptom codes).
    |
    */

    'symptom_keywords' => [

        'irregular_periods' => [
            'irregular periods', 'irregular period', 'irregular menses', 'irregular cycles', 'irregular cycle',
            'irregular menstruation', 'infrequent periods', 'infrequent period', 'infrequent menses',
            'unpredictable periods', 'periods are irregular', 'period is irregular', 'cycles are irregular',
            'periods come every # months', 'period comes every # months', 'periods every # months',
            'period every # months', 'periods once every # months', 'periods come and go',
            'oligomenorrhoea', 'oligomenorrhea', 'oligo-ovulation', 'oligoovulation',
            // Medical terms
            'oligomenorrhoeic', 'oligomenorrheic', 'oligo-menorrhoea', 'oligo-menorrhea', 'oligo-ovulatory',
            'oligoovulatory', 'irregular ovulation', 'menstrual irregularity', 'menstrual irregularities',
            'cycle irregularity', 'irregular menstrual cycles', 'irregular menstrual cycle', 'irregular menstrual periods',
            // Abbreviations and short clinic wording
            'irreg periods', 'irreg menses', 'irreg cycles', 'periods irregular', 'menses irregular',
            'cycles irregular', 'cycle irregular', 'menstrual cycles irregular', 'm/c irregular', 'mc irregular',
            // Everyday wording
            'erratic periods', 'erratic menses', 'erratic cycles', 'periods are erratic', 'periods erratic',
            'unpredictable cycles', 'unpredictable menses', 'periods are unpredictable', 'periods unpredictable',
            'variable cycles', 'cycle length varies', 'cycles vary', 'long cycles', 'skipped periods',
            'skips periods', 'skipping periods', 'delayed periods', 'periods delayed', 'late periods',
            'periods come late',
            // Number patterns
            'menses every # months', 'cycles every # months', 'cycle every # months',
            'periods # times a year', 'periods # times per year', 'period # times a year',
            // Cycles under 21 or over 35 days are irregular (2023 PCOS guideline).
            'cycles {<21|>35} days', 'cycle {<21|>35} days', 'cycles of {<21|>35} days', 'cycle of {<21|>35} days',
            'cycle length {<21|>35} days', 'cycle length of {<21|>35} days', 'cycle length is {<21|>35} days',
            'cycle lengths {<21|>35} days', 'cycles are {<21|>35} days', 'cycle is {<21|>35} days',
            'cycle lasts {<21|>35} days', 'cycles last {<21|>35} days', 'cycles every {<21|>35} days',
            'periods every {<21|>35} days', 'period every {<21|>35} days', 'menses every {<21|>35} days',
            '{<21|>35}-day cycles', '{<21|>35}-day cycle', '{<21|>35} day cycles', '{<21|>35} day cycle',
            // The same in weeks: under 3 weeks (21 days) or over 5 weeks (35 days).
            'cycles every {<3|>5} weeks', 'cycle every {<3|>5} weeks', 'periods every {<3|>5} weeks',
            'period every {<3|>5} weeks', 'menses every {<3|>5} weeks', 'cycles of {<3|>5} weeks',
            'cycle length {<3|>5} weeks', 'cycle length of {<3|>5} weeks',
        ],

        'absent_periods' => [
            'no period', 'no periods', 'no menses', 'missed period', 'missed periods', 'missing periods',
            "hasn't had her period", 'has not had her period', "hasn't had a period", 'has not had a period',
            "hasn't had any periods", "hasn't seen her period", 'has not seen her period',
            'periods have stopped', 'period has stopped', 'periods stopped', 'stopped having periods',
            'amenorrhoea', 'amenorrhea', 'anovulation', 'not ovulating',
            // Medical terms
            'amenorrhoeic', 'amenorrheic', 'anovulatory', 'cessation of menses',
            // Abbreviations and number patterns (LMP = last menstrual period)
            // Only 3 months or more (over 90 days) counts as absent periods.
            'lmp {3+} months ago', 'lmp {3+} months back', 'lmp was {3+} months ago', 'last period {3+} months ago',
            'last period was {3+} months ago', 'last menstrual period {3+} months ago',
            // The same in weeks (13 weeks = 91 days) and days.
            'lmp {13+} weeks ago', 'lmp was {13+} weeks ago', 'last period {13+} weeks ago',
            'last period was {13+} weeks ago', 'lmp {91+} days ago', 'last period {91+} days ago',
            // Everyday wording
            'no menstruation', 'no menstrual period', 'no menstrual periods', 'missed her period',
            'missed her periods', 'missed menses', "hasn't menstruated", 'has not menstruated',
            'not menstruating', 'stopped menstruating', 'not had a period', 'not had her period',
            'not had periods', "hasn't seen her periods", 'not seen her period', 'periods absent',
            'menses absent', 'absent periods', 'absent menses', 'absence of periods', 'absence of menses',
            'periods ceased', 'periods have ceased', 'menses ceased', 'menses stopped',
        ],

        'prolonged_bleeding' => [
            'prolonged bleeding', 'prolonged periods', 'prolonged period', 'prolonged menses',
            'long periods', 'periods last # weeks', 'period lasts # weeks', 'bleeding for # weeks',
            'bleeding for weeks', 'bleeding for # days', 'heavy bleeding', 'heavy periods', 'heavy period',
            'menorrhagia', 'menometrorrhagia', 'bleeds for weeks',
            // Medical terms and abbreviations (HMB = heavy menstrual bleeding)
            'hmb', 'heavy menstrual bleeding', 'metrorrhagia', 'prolonged menstruation',
            'prolonged menstrual bleeding', 'prolonged vaginal bleeding',
            // Everyday wording
            'heavy menses', 'heavy menstruation', 'heavy flow', 'heavy menstrual flow', 'long menses',
            'continuous bleeding', 'bleeding continuously', 'passing clots', 'soaking pads',
            // Number patterns
            'menses last # weeks', 'menses lasting # weeks', 'periods lasting # weeks', 'period lasting # weeks',
            'bleeds for # weeks', 'bleeding for # months',
        ],

        'excess_hair_growth' => [
            // "excess hair" is under nearby_words instead, so "excessive hair loss" isn't matched.
            'hair growth', 'facial hair', 'hair on her face', 'hair on the face',
            'hair on her chin', 'chin hair', 'beard', 'moustache', 'mustache', 'hairy chest', 'hirsutism',
            'hirsute', 'body hair',
            // Medical terms
            'male pattern hair growth', 'male-pattern hair growth', 'terminal hair', 'coarse hair', 'coarse dark hair',
            // Everyday wording
            'facial hairs', 'chin hairs', 'hair on chin', 'hair on the chin', 'hair on upper lip',
            'hair on her upper lip', 'upper lip hair', 'hairy face', 'hair on her chest', 'hair on the chest',
            'chest hair', 'hair on her abdomen', 'abdominal hair', 'hair on her back', 'body hairs',
            'shaves her face', 'shaves her chin', 'plucks her chin', 'plucks facial hair',
        ],

        'acne' => [
            'acne', 'pimples', 'pimple', 'spots on her face', 'breakouts', 'oily skin with spots',
            // Medical terms
            'comedones', 'comedonal', 'blackheads', 'whiteheads',
            // Everyday wording
            'spots on face', 'spots on the face', 'facial spots', 'pimply', 'breakout', 'zits',
        ],

        'hair_thinning' => [
            'hair thinning', 'thinning hair', 'thin hair', 'hair loss', 'losing hair', 'losing her hair',
            'hair falling out', 'balding', 'bald patches', 'alopecia', 'receding hairline',
            // Medical terms
            'male pattern baldness', 'baldness',
            // Everyday wording
            'thinning of hair', 'thinning of the hair', 'thinning of scalp hair', 'scalp hair thinning',
            'thinning scalp hair', 'hair is thinning', 'receding hair line', 'hairline receding', 'bald spots',
            'hair shedding', 'shedding hair', 'hair falls out', 'hair is falling out', 'loss of hair',
            'loss of scalp hair', 'losing scalp hair', 'sparse hair',
        ],

        'weight_gain' => [
            'weight gain', 'gained weight', 'gaining weight', 'put on weight', 'putting on weight',
            'increase in weight', 'weight increase',
            // Abbreviations
            'wt gain', 'wt gained',
            // Everyday wording
            'weight gained', 'weight increased', 'increased weight', 'weight has increased', 'increasing weight',
            'weight has gone up', 'weight going up',
            // Number patterns ("#kg" also matches "8kg" with no space)
            'gained # kg', 'gained #kg', 'gained # kgs', 'gaining # kg', 'put on # kg', 'put on #kg',
            '# kg weight gain', '#kg weight gain', 'weight up by # kg',
        ],

        'dark_skin_patches' => [
            'dark skin patches', 'dark patches', 'darkened skin', 'darkening of the skin', 'dark skin on her neck',
            'dark neck', 'velvety skin', 'acanthosis', 'acanthosis nigricans',
            // Medical terms
            'hyperpigmented patches', 'hyperpigmentation of the neck', 'hyperpigmentation on the neck',
            'neck hyperpigmentation',
            // Everyday wording
            'velvety patches', 'velvety dark skin', 'skin darkening', 'darkening of skin', 'darkened patches',
            'dark skin folds', 'dark armpits', 'dark skin in the armpits', 'darkening of the neck',
            'darkening around the neck', 'neck darkening',
        ],

        'difficulty_conceiving' => [
            'difficulty conceiving', 'difficulty getting pregnant', 'trouble conceiving', 'trouble getting pregnant',
            'unable to conceive', 'unable to get pregnant', "can't get pregnant", 'cannot get pregnant',
            'not getting pregnant', 'trying to conceive', 'trying for a baby', 'infertility', 'infertile',
            'subfertility',
            // Medical terms and abbreviations (TTC = trying to conceive)
            'sub-fertility', 'subfertile', 'failure to conceive', 'delayed conception', 'ttc',
            // Everyday wording, British and American
            'failed to conceive', 'not conceiving', 'not able to conceive', 'has not conceived', "hasn't conceived",
            'unable to fall pregnant', 'struggling to conceive', 'struggling to get pregnant',
            'struggling to fall pregnant', 'difficulty falling pregnant', 'trying to get pregnant',
            'trying to fall pregnant', 'trying for a child',
        ],

        'polycystic_ovaries_on_ultrasound' => [
            'polycystic ovaries', 'polycystic ovary', 'polycystic ovarian morphology', 'pcom',
            'multiple follicles', 'many follicles', 'multifollicular ovaries', 'string of pearls',
            'cysts on her ovaries', 'cysts on the ovaries', 'ovarian cysts',
            // Medical terms and abbreviations (PCO = polycystic ovaries)
            'pco', 'pco morphology', 'polycystic appearing ovaries', 'polycystic-appearing ovaries',
            'polycystic appearance', 'polycystic looking ovaries', 'peripheral follicles', 'follicles per ovary',
            'necklace sign', 'pearl necklace',
            // Everyday wording
            'multiple small follicles', 'many small follicles', 'numerous follicles', 'numerous small follicles',
            'multiple peripheral follicles',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Nearby words
    |--------------------------------------------------------------------------
    |
    | If no phrase matches, one word from each set close together in a sentence also counts,
    | e.g. "scalp hair is getting thin". "unless" words cancel the group. Negatives still apply.
    |
    */

    'nearby_words' => [

        'enabled' => true,

        'max_words_between' => 3,

        'groups' => [

            'irregular_periods' => [
                [
                    'periods|period|menses|menstruation|cycles|cycle', 'irregular|erratic|unpredictable|infrequent|irreg',
                    'unless' => 'sleep|pulse|heartbeat|heart|rhythm|breathing|bowel|bowels',
                ],
            ],

            'absent_periods' => [
                ['periods|period|menses|menstruation', 'stopped|ceased|absent|missed'],
            ],

            'prolonged_bleeding' => [
                [
                    'periods|menses|menstruation|bleeding|bleeds|bleed|flow', 'heavy|prolonged|excessive|lengthy',
                    'unless' => 'gums|nose|nosebleed|wound|rectal|stool|stools|urine|smoker|smoking|drinker|drinking|workload|lifting',
                ],
            ],

            'excess_hair_growth' => [
                [
                    'hair|hairs', 'excess|excessive|coarse|unwanted|abnormal',
                    'unless' => 'loss|losing|lost|thinning|thin|falling|shedding|bald|balding|scalp',
                ],
                [
                    'hair|hairs|hairy', 'chin|lip|face|facial|jaw|jawline|chest|nipples|breasts|abdomen|abdominal|sideburns',
                    'unless' => 'loss|losing|lost|thinning|thin|falling|shedding|bald|balding|scalp',
                ],
            ],

            'acne' => [
                [
                    'spots|bumps', 'face|facial|forehead|cheeks|chin|jawline',
                    'unless' => 'dark|brown|black|white|pigmented|hyperpigmented|pigmentation',
                ],
            ],

            'hair_thinning' => [
                ['hair|hairs|hairline|scalp', 'thin|thinner|thinning|loss|losing|lost|falling|shedding|receding|sparse|balding|bald'],
            ],

            'weight_gain' => [
                [
                    'weight|wt', 'up|gain|gained|gaining|increase|increased|increasing|rising|risen',
                    'unless' => 'loss|lost|losing|down|reduced|dropped|follow|bearing',
                ],
            ],

            'dark_skin_patches' => [
                [
                    'neck|armpits|armpit|axilla|axillae|axillary|groin|knuckles|folds', 'dark|darker|darkened|darkening|velvety|hyperpigmented|hyperpigmentation|pigmented',
                    'unless' => 'urine|eyes|stool|stools|hair|circles',
                ],
                ['skin', 'velvety'],
            ],

            'difficulty_conceiving' => [
                ['conceive|conceiving|conception', 'unable|inability|failure|failed|difficulty|difficulties|trouble|struggling|trying|delayed|problems'],
                [
                    'pregnant', 'unable|failed|difficulty|trouble|struggling|trying',
                    'unless' => 'avoid|avoiding|prevent|contraception|contraceptive|test',
                ],
            ],

            'polycystic_ovaries_on_ultrasound' => [
                [
                    'follicles|follicle', 'multiple|many|numerous|peripheral|peripherally',
                    'unless' => 'scalp|hair|folliculitis|skin',
                ],
                ['ovaries|ovary', 'polycystic'],
            ],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Clinical shorthand
    |--------------------------------------------------------------------------
    |
    | Shorthand is written out before searching, e.g. "4/12" = 4 months, "q3" = every 3.
    | Dates and blood pressures are left alone. The doctor still sees their own words.
    |
    */

    'shorthand' => [

        'fractions' => [
            '12' => 'months',
            '52' => 'weeks',
            '7' => 'days',
        ],

        'before_numbers' => [
            'q' => 'every',
            'x' => 'for',
        ],

        'words' => [
            'yrs' => 'years', 'yr' => 'year', 'wks' => 'weeks', 'wk' => 'week',
            'mths' => 'months', 'mth' => 'month', 'mnths' => 'months', 'mos' => 'months',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored phrases
    |--------------------------------------------------------------------------
    |
    | Phrases that have symptom words but aren't the symptom, e.g. "polycystic ovary syndrome"
    | (a diagnosis) or "fever for a period of two days".
    |
    */

    'ignored_phrases' => [
        'polycystic ovary syndrome', 'polycystic ovaries syndrome', 'polycystic ovarian syndrome',
        'polycystic ovary disease', 'polycystic ovarian disease', 'polymetabolic ovarian syndrome',
        'polymetabolic ovary syndrome', 'pcos', 'pcod', 'pmos',
        'dark spots', 'brown spots', 'black spots',
        'a period of', 'for long periods',
    ],

    /*
    |--------------------------------------------------------------------------
    | Spelling tolerance
    |--------------------------------------------------------------------------
    |
    | Allows small typos like "hirsutsm". Longer words allow more mistakes, short words must be exact.
    | Words in "never_fuzzy" are always exact because they're too easy to mix up.
    |
    */

    'spelling_tolerance' => [
        'enabled' => true,
        'one_mistake_from_letters' => 5,
        'two_mistakes_from_letters' => 8,
        'first_letter_must_match' => true,
        'never_fuzzy' => [
            'acme', 'acre',     // look like "acne"
            'regular',          // the opposite of "irregular"
            'beard',            // too close to "bears", "beads", "bread"
            'pregnancy',        // "unable to get a pregnancy test" is not "unable to get pregnant"
            'infantile',        // looks like "infertile"
            'banding',          // "gastric banding" is not "balding"
            'boldness',         // looks like "baldness"
            'thinking',         // looks like "thinning"
            'length',           // "cycle length" is not "lengthy" bleeding
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Negative words
    |--------------------------------------------------------------------------
    |
    | A phrase isn't matched if one of these comes just before it, e.g. "no acne".
    | Only the same part of the sentence counts, so "no fever, but has acne" still finds acne.
    |
    */

    'negative_words' => [
        'no', 'not', 'denies', 'denied', 'without', 'never', 'negative for', 'absence of', 'free of',
        "doesn't have", 'does not have', "hasn't got", 'nil', 'neither', 'nor',
    ],

    /*
    | How many words before a phrase are checked for a negative word.
    */

    'negative_word_window' => 3,

    /*
    |--------------------------------------------------------------------------
    | Negative lists
    |--------------------------------------------------------------------------
    |
    | A negative at the start of a list covers every item, e.g. "No hirsutism, acne or alopecia".
    | A part with numbers or "finding_words" ("weight up 5 kg") is a new finding, not a list item.
    |
    */

    'negative_lists' => [
        'enabled' => true,
        'joining_words' => ['or', 'and', 'nor'],
        'needs_joining_word' => true,
        'max_item_words' => 4,
        'finding_words' => [
            'is', 'are', 'was', 'were', 'has', 'have', 'had', 'shows', 'showed', 'reports', 'reported',
            'complains', 'noted', 'seen', 'found', 'present', 'started', 'up', 'down', 'increased',
            'decreased', 'raised', 'elevated', 'positive', 'worse', 'worsening', 'better', 'improved',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Negative words after a symptom
    |--------------------------------------------------------------------------
    |
    | Also not matched if one of these comes just after it, e.g. "acne: none". Kept short so
    | "amenorrhoea with pregnancy ruled out" still counts. "negative" is left out (usually a test result).
    |
    */

    'negative_words_after' => [
        'none', 'nil', 'absent', 'not present', 'not seen', 'not noted', 'not observed', 'not detected',
        'not found', 'ruled out', 'excluded', 'denied',
        'not a feature', 'not a problem', 'not an issue', 'not evident', 'not apparent', 'not elicited',
        'not reported', 'not significant', 'nad',   // NAD = nothing abnormal detected
    ],

    /*
    | How many words after a phrase a negative word may start in.
    */

    'negative_word_window_after' => 2,

    /*
    |--------------------------------------------------------------------------
    | Statistics report
    |--------------------------------------------------------------------------
    |
    | Age groups (age when PMOS was confirmed), WHO BMI groups, and how many weeks
    | before a referral with no result shows up as overdue.
    |
    */

    'report' => [

        'age_groups' => [
            ['label' => 'Under 20', 'to' => 19],
            ['label' => '20-24', 'from' => 20, 'to' => 24],
            ['label' => '25-29', 'from' => 25, 'to' => 29],
            ['label' => '30-34', 'from' => 30, 'to' => 34],
            ['label' => '35-39', 'from' => 35, 'to' => 39],
            ['label' => '40 and over', 'from' => 40],
        ],

        'bmi_groups' => [
            ['label' => 'Underweight', 'below' => 18.5],
            ['label' => 'Normal', 'from' => 18.5, 'below' => 25],
            ['label' => 'Overweight', 'from' => 25, 'below' => 30],
            ['label' => 'Obese', 'from' => 30],
        ],

        'overdue_results_after_weeks' => 6,

    ],

];
