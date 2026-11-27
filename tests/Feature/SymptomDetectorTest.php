<?php

namespace Tests\Feature;

use App\Screening\DetectedSymptom;
use App\Screening\SymptomDetector;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SymptomDetectorTest extends TestCase
{
    /**
     * The symptom codes the detector finds in the notes
     *
     * @return list<string>
     */
    private function codesFoundIn(string $notes): array
    {
        return array_map(fn (DetectedSymptom $symptom): string => $symptom->code, (new SymptomDetector)->detect($notes));
    }

    public function test_notes_about_an_unrelated_illness_find_nothing(): void
    {
        $this->assertSame([], $this->codesFoundIn('Headache and runny nose for 3 days. Fever 38.5.'));
        $this->assertSame([], (new SymptomDetector)->detect(null));
    }

    public function test_it_finds_symptoms_written_in_everyday_words(): void
    {
        $this->assertSame(
            ['absent_periods', 'excess_hair_growth'],
            $this->codesFoundIn("Hasn't had her period in 3 months, not pregnant. Growing facial hair."),
        );
    }

    #[TestWith(['Periods come every 3 months'])]
    #[TestWith(['her periods come every three months'])]
    #[TestWith(['periods every 2-3 months'])]
    #[TestWith(['Period comes every few months'])]
    #[TestWith(['Infrequent periods since age 16'])]
    #[TestWith(['oligomenorrhoea'])]
    public function test_less_obvious_wording_is_recognised_as_irregular_periods(string $notes): void
    {
        $this->assertSame(['irregular_periods'], $this->codesFoundIn($notes));
    }

    #[TestWith(['No acne.'])]
    #[TestWith(['Denies facial hair'])]
    #[TestWith(['no acne or hirsutism'])]
    #[TestWith(['She does not have pimples'])]
    #[TestWith(['Negative for hirsutism'])]
    #[TestWith(['Neither acne nor hirsutism'])]
    public function test_simple_negatives_are_not_matched(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    #[TestWith(['Acne: none'])]
    #[TestWith(['Acne absent'])]
    #[TestWith(['Hirsutism - nil'])]
    #[TestWith(['Acne not present'])]
    #[TestWith(['Facial hair is absent'])]
    #[TestWith(['Polycystic ovaries ruled out on scan'])]
    #[TestWith(['Hirsutism not a feature'])]
    #[TestWith(['Acne: not evident'])]
    #[TestWith(['Hair loss not observed'])]
    #[TestWith(['Weight gain not reported'])]
    #[TestWith(['Hirsutism - NAD'])]
    #[TestWith(['Acne nil'])]
    public function test_negatives_after_the_symptom_are_not_matched(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    #[TestWith(['Amenorrhoea with pregnancy ruled out', 'absent_periods'])]
    #[TestWith(['Amenorrhoea, UPT negative', 'absent_periods'])]
    #[TestWith(['Hirsutism absent, acne present', 'acne'])]
    #[TestWith(['Periods absent for 6 months', 'absent_periods'])]
    public function test_a_negative_about_something_else_does_not_cancel_the_symptom(string $notes, string $expectedCode): void
    {
        $this->assertSame([$expectedCode], $this->codesFoundIn($notes));
    }

    public function test_the_negatives_after_a_symptom_come_from_the_rules_file(): void
    {
        config()->set('screening.negative_words_after', ['gone']);

        $this->assertSame([], $this->codesFoundIn('Acne gone'));
        $this->assertSame(['acne'], $this->codesFoundIn('Acne: none'));
    }

    public function test_a_negative_in_another_part_of_the_sentence_does_not_cancel_a_match(): void
    {
        $this->assertSame(['acne'], $this->codesFoundIn('No fever, but has acne on her chin'));
    }

    public function test_no_period_counts_as_absent_periods_because_no_is_part_of_the_phrase(): void
    {
        $this->assertSame(['absent_periods'], $this->codesFoundIn('No period since June'));
    }

    public function test_only_whole_words_match(): void
    {
        $this->assertSame([], $this->codesFoundIn('New facial hairstyle; acneiform rash ruled out'));
    }

    public function test_capital_letters_and_curly_apostrophes_do_not_stop_a_match(): void
    {
        $this->assertSame(['absent_periods'], $this->codesFoundIn('HASN’T HAD HER PERIOD SINCE MAY'));
    }

    public function test_each_symptom_is_found_once_with_the_words_it_came_from(): void
    {
        $found = (new SymptomDetector)->detect('Some acne on her back. Pimples on her forehead too.');

        $this->assertCount(1, $found);
        $this->assertSame('acne', $found[0]->code);
        $this->assertSame('acne', $found[0]->matchedWords);
        $this->assertSame('Some acne on her back', $found[0]->excerpt);
        $this->assertSame(['before' => 'Some ', 'match' => 'acne', 'after' => ' on her back'], $found[0]->excerptParts());
    }

    #[TestWith(['Hirsutsm on her chin', 'excess_hair_growth'])]
    #[TestWith(['Irregullar menses', 'irregular_periods'])]
    #[TestWith(['irregualr periods', 'irregular_periods'])]
    #[TestWith(['Secondary amenorhea', 'absent_periods'])]
    #[TestWith(['She hasnt had her period since May', 'absent_periods'])]
    #[TestWith(['Pimpels on her forehead', 'acne'])]
    #[TestWith(['polycistic ovaries on scan', 'polycystic_ovaries_on_ultrasound'])]
    public function test_small_spelling_mistakes_are_tolerated(string $notes, string $expectedCode): void
    {
        $this->assertSame([$expectedCode], $this->codesFoundIn($notes));
    }

    public function test_the_matched_words_are_shown_as_the_doctor_typed_them(): void
    {
        $found = (new SymptomDetector)->detect('Hirsutsm on chin and upper lip.');

        $this->assertSame('Hirsutsm', $found[0]->matchedWords);
        $this->assertSame(['before' => '', 'match' => 'Hirsutsm', 'after' => ' on chin and upper lip'], $found[0]->excerptParts());
    }

    #[TestWith(['Acme of her career'])]
    #[TestWith(['Lives on a 2 acre farm'])]
    #[TestWith(['Periods regular'])]
    #[TestWith(['Dysmenorrhoea every month'])]
    #[TestWith(['Had gastric banding in 2020'])]
    #[TestWith(['Unable to get pregnancy test today'])]
    #[TestWith(['Infantile colic in her baby'])]
    #[TestWith(['Hersootizm'])]
    public function test_look_alike_words_and_bigger_mistakes_are_not_matched(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    public function test_spelling_tolerance_limits_come_from_the_rules_file(): void
    {
        config()->set('screening.spelling_tolerance.enabled', false);
        $this->assertSame([], $this->codesFoundIn('Hirsutsm on her chin'));

        config()->set('screening.spelling_tolerance.enabled', true);
        config()->set('screening.spelling_tolerance.two_mistakes_from_letters', 99);
        $this->assertSame(['excess_hair_growth'], $this->codesFoundIn('Hirsutsm on her chin'));
        $this->assertSame([], $this->codesFoundIn('Hrsutsm on her chin'));
    }

    #[TestWith(['LMP 5 months ago', 'absent_periods'])]
    #[TestWith(['HMB for a year', 'prolonged_bleeding'])]
    #[TestWith(['USS: PCO morphology', 'polycystic_ovaries_on_ultrasound'])]
    #[TestWith(['Wt gain 8kg', 'weight_gain'])]
    #[TestWith(['Has gained 10 kg in 6 months', 'weight_gain'])]
    #[TestWith(['Struggling to fall pregnant', 'difficulty_conceiving'])]
    #[TestWith(['Periods are erratic', 'irregular_periods'])]
    #[TestWith(['Dark velvety patches on her neck', 'dark_skin_patches'])]
    #[TestWith(['Thinning of scalp hair', 'hair_thinning'])]
    #[TestWith(['Comedones on her forehead', 'acne'])]
    public function test_abbreviations_medical_terms_and_everyday_wording_are_recognised(string $notes, string $expectedCode): void
    {
        $this->assertSame([$expectedCode], $this->codesFoundIn($notes));
    }

    #[TestWith(['LMP 2 weeks ago'])]
    #[TestWith(['Periods regular, last 5 days'])]
    #[TestWith(['Cycles of 28 days'])]
    #[TestWith(['Known PCOS on metformin'])]
    public function test_normal_menstrual_details_are_not_suggested(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    #[TestWith(['Known polycystic ovary syndrome, on metformin'])]
    #[TestWith(['Polycystic ovarian syndrome diagnosed in 2021'])]
    #[TestWith(['Polycystic ovary syndrom, here for review'])]
    #[TestWith(['Referred for PMOS screening'])]
    #[TestWith(['?PCOS'])]
    public function test_a_diagnosis_name_is_not_suggested_as_the_ultrasound_finding(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    public function test_the_ultrasound_finding_is_still_found_next_to_a_diagnosis_name(): void
    {
        $this->assertSame(['polycystic_ovaries_on_ultrasound'], $this->codesFoundIn('USS shows polycystic ovaries, consistent with PCOS'));
    }

    #[TestWith(['LMP 3 months ago', true])]
    #[TestWith(['LMP five months ago', true])]
    #[TestWith(['Last period was 4 months ago', true])]
    #[TestWith(['LMP 2-3 months ago', true])]
    #[TestWith(['LMP 2 months ago', false])]
    #[TestWith(['LMP one month ago', false])]
    #[TestWith(['LMP 6 weeks ago', false])]
    public function test_lmp_only_suggests_absent_periods_from_3_months(string $notes, bool $suggested): void
    {
        $this->assertSame($suggested ? ['absent_periods'] : [], $this->codesFoundIn($notes));
    }

    #[TestWith(['Cycles 40-60 days', true])]
    #[TestWith(['Cycle length 45 days', true])]
    #[TestWith(['Cycles of 18 days', true])]
    #[TestWith(['45-day cycles', true])]
    #[TestWith(['Periods every 14 days', true])]
    #[TestWith(['Cycles 28-40 days', true])]
    #[TestWith(['Cycles 21-35 days', false])]
    #[TestWith(['Cycle length 30 days', false])]
    #[TestWith(['28 day cycles', false])]
    #[TestWith(['Periods every 28 days', false])]
    #[TestWith(['Cycle length 35 days', false])]
    #[TestWith(['Cycle length 21 days', false])]
    public function test_cycles_shorter_than_21_or_longer_than_35_days_suggest_irregular_periods(string $notes, bool $suggested): void
    {
        $this->assertSame($suggested ? ['irregular_periods'] : [], $this->codesFoundIn($notes));
    }

    #[TestWith(['Scalp hair is getting thin', 'hair_thinning'])]
    #[TestWith(['Periods heavy and last 9-10 days', 'prolonged_bleeding'])]
    #[TestWith(['Weight up 12kg since last year', 'weight_gain'])]
    #[TestWith(['Small follicles arranged peripherally', 'polycystic_ovaries_on_ultrasound'])]
    #[TestWith(['Her menses have been very irregular', 'irregular_periods'])]
    #[TestWith(['Coarse dark hair over the chin', 'excess_hair_growth'])]
    #[TestWith(['Both ovaries look polycystic', 'polycystic_ovaries_on_ultrasound'])]
    public function test_key_words_close_together_in_any_order_are_recognised(string $notes, string $expectedCode): void
    {
        $this->assertSame([$expectedCode], $this->codesFoundIn($notes));
    }

    public function test_nearby_words_show_the_doctors_own_words_from_first_to_last(): void
    {
        $found = (new SymptomDetector)->detect('Scalp hair is getting thin at the front.');

        $this->assertSame('hair is getting thin', $found[0]->matchedWords);
    }

    #[TestWith(['Periods not heavy'])]
    #[TestWith(['No thinning of the scalp hair'])]
    #[TestWith(['Weight increase: none'])]
    #[TestWith(['Hair on her chin is absent'])]
    public function test_negatives_still_apply_to_nearby_words(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    #[TestWith(['Weight check at follow up visit'])]
    #[TestWith(['Weight down 3 kg since last visit'])]
    #[TestWith(['Dark spots on her face from old scars'])]
    #[TestWith(['Trying to avoid getting pregnant'])]
    #[TestWith(['Dark-skinned lady, BP normal'])]
    #[TestWith(['A heavy period of work stress, now resolved, still very busy at the office'])]
    public function test_nearby_words_avoid_common_false_matches(string $notes): void
    {
        $this->assertNotContains('weight_gain', $this->codesFoundIn($notes));
        $this->assertNotContains('acne', $this->codesFoundIn($notes));
        $this->assertNotContains('difficulty_conceiving', $this->codesFoundIn($notes));
        $this->assertNotContains('dark_skin_patches', $this->codesFoundIn($notes));
    }

    public function test_excessive_hair_loss_is_hair_thinning_not_hair_growth(): void
    {
        $this->assertSame(['hair_thinning'], $this->codesFoundIn('Excessive hair loss for a year'));
    }

    public function test_words_too_far_apart_do_not_match(): void
    {
        $this->assertSame([], $this->codesFoundIn('Hair braided neatly and nicely for the wedding, very thin build'));
    }

    #[TestWith(['Weight 92 kg, up from 78 kg last year', 'weight_gain'])]
    #[TestWith(['Periods, very heavy', 'prolonged_bleeding'])]
    #[TestWith(['Scalp hair, thinning at the crown', 'hair_thinning'])]
    public function test_nearby_words_may_be_separated_by_a_comma(string $notes, string $expectedCode): void
    {
        $this->assertSame([$expectedCode], $this->codesFoundIn($notes));
    }

    #[TestWith(['Weight 92 kg. Up from 78 kg last year'])]
    #[TestWith(['Weight 92 kg; up the dose of metformin'])]
    #[TestWith(['Weight stable but BP up'])]
    #[TestWith(['Periods, cycle length 28 days'])]
    #[TestWith(['Weight checked however the BP is up'])]
    public function test_nearby_words_are_not_matched_across_sentences(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    #[TestWith(['Blood pressure up, weight recorded as 64 kg'])]
    #[TestWith(['Thin build, hair neat, alert and oriented'])]
    #[TestWith(['Facial swelling after a bee sting, hair and nails normal'])]
    public function test_across_a_comma_the_main_word_must_come_first(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    #[TestWith(['Bleeding gums when brushing, heavy smoker'])]
    #[TestWith(['Lymph node in the neck, dark urine'])]
    #[TestWith(['Follicles on the scalp inflamed, multiple pustules'])]
    #[TestWith(['Cycles regular, irregular sleep due to night shifts'])]
    #[TestWith(['Weight bearing is painful, swelling up to the knee'])]
    #[TestWith(['Fever for a period of two days with heavy sweating'])]
    #[TestWith(['She stands for long periods at work'])]
    public function test_key_words_used_about_something_else_are_not_matched(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    #[TestWith(['No hirsutism, acne or alopecia'])]
    #[TestWith(['No hirsutism, acne or alopecia on examination'])]
    #[TestWith(['Denies weight gain, hair loss or irregular cycles'])]
    #[TestWith(['No acne, facial hair, hair thinning or dark patches'])]
    #[TestWith(['Denies missed periods, heavy bleeding and weight gain'])]
    #[TestWith(['Nil hirsutism, acne nor alopecia'])]
    public function test_a_negative_at_the_start_of_a_list_covers_every_item(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    #[TestWith(['No fever, weight up 5 kg', ['weight_gain']])]
    #[TestWith(['No fever, acne present on her chin', ['acne']])]
    #[TestWith(['No fever, acne on face', ['acne']])]
    #[TestWith(['No acne or hirsutism, weight gain of 10 kg', ['weight_gain']])]
    #[TestWith(['No hirsutism, acne or alopecia. Weight gain this year', ['weight_gain']])]
    #[TestWith(['No hirsutism, but acne and hair loss', ['acne', 'hair_thinning']])]
    #[TestWith(['No hirsutism; acne or alopecia', ['acne', 'hair_thinning']])]
    #[TestWith(['No cough, hair thinning noted or worsening', ['hair_thinning']])]
    public function test_a_negative_list_ends_where_a_new_finding_starts(string $notes, array $expected): void
    {
        $this->assertSame($expected, $this->codesFoundIn($notes));
    }

    public function test_the_negative_list_settings_come_from_the_rules_file(): void
    {
        $this->assertSame(['acne', 'hair_thinning'], $this->codesFoundIn('No hirsutism, acne, alopecia'));

        config()->set('screening.negative_lists.needs_joining_word', false);
        $this->assertSame([], $this->codesFoundIn('No hirsutism, acne, alopecia'));

        config()->set('screening.negative_lists.enabled', false);
        $this->assertSame(['acne', 'hair_thinning'], $this->codesFoundIn('No hirsutism, acne or alopecia'));
    }

    public function test_a_negative_across_a_comma_only_applies_to_its_own_part_of_the_sentence(): void
    {
        $this->assertSame(['weight_gain'], $this->codesFoundIn('No fever, weight up 5 kg'));
        $this->assertSame([], $this->codesFoundIn('Scalp hair, no thinning'));
        $this->assertSame([], $this->codesFoundIn('Periods, heavy: none'));
    }

    public function test_the_nearby_word_distance_comes_from_the_rules_file(): void
    {
        config()->set('screening.nearby_words.max_words_between', 1);
        $this->assertSame([], $this->codesFoundIn('Scalp hair is getting thin'));

        config()->set('screening.nearby_words.max_words_between', 3);
        $this->assertSame(['hair_thinning'], $this->codesFoundIn('Scalp hair is getting thin'));

        config()->set('screening.nearby_words.enabled', false);
        $this->assertSame([], $this->codesFoundIn('Scalp hair is getting thin'));
    }

    #[TestWith(['LMP 4/12 ago', ['absent_periods']])]
    #[TestWith(['LMP 2/12 ago', []])]
    #[TestWith(['LMP 14/52 ago', ['absent_periods']])]
    #[TestWith(['LMP 6/52 ago', []])]
    #[TestWith(['Menses q2-4 months', ['irregular_periods']])]
    #[TestWith(['Menses q3/12', ['irregular_periods']])]
    #[TestWith(['Bleeding x 3/52', ['prolonged_bleeding']])]
    #[TestWith(['Cycles every 6-10 wks', ['irregular_periods']])]
    #[TestWith(['Cycles every 4 weeks', []])]
    public function test_clinical_shorthand_is_understood(string $notes, array $expected): void
    {
        $this->assertSame($expected, $this->codesFoundIn($notes));
    }

    public function test_shorthand_is_shown_as_the_doctor_wrote_it(): void
    {
        $found = (new SymptomDetector)->detect('LMP 4/12 ago, UPT negative.');

        $this->assertSame('LMP 4/12 ago', $found[0]->matchedWords);
        $this->assertSame('LMP 4/12 ago', $found[0]->excerpt);
    }

    #[TestWith(['Seen on 03/12/2025 for a cough'])]
    #[TestWith(['BP 120/70, pulse 72'])]
    #[TestWith(['X-ray of the chest normal'])]
    public function test_dates_blood_pressures_and_other_slashes_are_left_alone(string $notes): void
    {
        $this->assertSame([], $this->codesFoundIn($notes));
    }

    public function test_number_rules_come_from_the_rules_file(): void
    {
        config()->set('screening.symptom_keywords.absent_periods', ['lmp {6+} months ago']);

        $this->assertSame([], $this->codesFoundIn('LMP 4 months ago'));
        $this->assertSame(['absent_periods'], $this->codesFoundIn('LMP 7 months ago'));
    }

    public function test_keywords_come_from_the_rules_file_so_they_can_be_changed_without_code(): void
    {
        config()->set('screening.symptom_keywords.acne', ['zits']);

        $this->assertSame(['acne'], $this->codesFoundIn('Zits on her cheeks'));
        $this->assertSame([], $this->codesFoundIn('Acne on her cheeks'));
    }
}
