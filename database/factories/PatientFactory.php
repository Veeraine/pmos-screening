<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use App\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public const FIRST_NAMES = [
        'Chimwemwe', 'Chifundo', 'Tadala', 'Takondwa', 'Mwayi', 'Tamanda', 'Atupele', 'Wezi',
        'Tiyamike', 'Madalitso', 'Yamikani', 'Pemphero', 'Alinafe', 'Ulemu', 'Tawonga', 'Esnart',
        'Agness', 'Memory', 'Dalitso', 'Khumbo', 'Tionge', 'Fanny', 'Loveness', 'Ruth',
    ];

    public const LAST_NAMES = [
        'Gondwe', 'Mhango', 'Msiska', 'Mbewe', 'Chisale', 'Kachingwe', 'Mkandawire', 'Munthali',
        'Chimwaza', 'Kalua', 'Moyo', 'Zulu', 'Ngwira', 'Tembo', 'Lungu', 'Mvula', 'Kanyenda',
        'Chiumia', 'Kaunda', 'Mpinganjira', 'Chikoti', 'Saidi', 'Makwinja', 'Nkhoma',
    ];

    public const ADDRESSES = [
        'Chitawira, Blantyre', 'Ndirande, Blantyre', 'Chilomoni, Blantyre', 'Limbe, Blantyre',
        'Bangwe, Blantyre', 'Soche, Blantyre', 'Zingwangwa, Blantyre', 'Chirimba, Blantyre',
        'Namiwawa, Blantyre', 'Mandala, Blantyre', 'Sunnyside, Blantyre', 'Mbayani, Blantyre',
        'Kanjedza, Blantyre', 'Nkolokosi, Blantyre', 'Chileka, Blantyre', 'Lunzu, Blantyre',
        'Machinjiri, Blantyre', 'Chigumula, Blantyre', 'Manja, Blantyre', 'Chiradzulu',
        'Thyolo', 'Mulanje', 'Zomba', 'Balaka', 'Area 25, Lilongwe',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->randomElement(self::FIRST_NAMES),
            'last_name' => fake()->randomElement(self::LAST_NAMES),
            'date_of_birth' => fake()->dateTimeBetween('-45 years', '-15 years')->format('Y-m-d'),
            'phone' => fake()->numerify(fake()->randomElement(['+265 99# ### ###', '+265 88# ### ###'])),
            'address' => fake()->optional(0.8)->randomElement(self::ADDRESSES),
            'previous_diagnoses' => null,
            'registered_by' => User::factory()->role(Role::Nurse),
        ];
    }
}
