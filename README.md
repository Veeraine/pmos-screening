# PMOS Clinical Screening Support System

**Development of a Polyendocrine Metabolic Ovarian Syndrome Clinical Screening Support System: A Case Study of Chitawira Private Hospital**

Lorraine Vera Jere (BIS/22/SS/008) · Bachelor of Information Systems · Malawi University of Business and Applied Sciences (MUBAS)

> PMOS was formerly called PCOS (polycystic ovary syndrome).

## Open the system

- **Use it online (no installation):** https://pmos-screening.onrender.com
  (the first visit can take up to a minute to load)
- **Installation and user guide:** in the PDF guide submitted with the project report

## Demo logins

| Role | Email | Password |
|---|---|---|
| Doctor | doctor@chitawira.test | password |
| Nurse | nurse@chitawira.test | password |
| Administrator | admin@chitawira.test | password |

## Quick install (Windows)

1. Install Laravel Herd (free): https://herd.laravel.com/download/windows, then restart the computer.
2. On this page, click **Code**, then **Download ZIP**. Right-click the ZIP, choose **Properties**, tick **Unblock**, then **Extract All**.
3. In the extracted folder, double-click **start-pmos.bat**. The first time takes about 5 to 10 minutes.
4. The browser opens at http://127.0.0.1:8000. Log in with a demo account.

Full steps, a backup way and help with errors are in the PDF installation and user guide.

## What the system does

The system is an add-on to the hospital's electronic medical record (EMR). It helps doctors notice patients who may have PMOS so they can be referred for screening.

1. A **nurse** registers the patient, checks them in and records vital signs.
2. A **doctor** writes consultation notes in their own words. The system finds PMOS-related symptoms in the notes and asks the doctor to confirm or remove each one.
3. After each visit, the system checks all of the patient's confirmed symptoms against the **Rotterdam criteria**. When 2 of the 3 signs are found, it raises a **screening alert**.
4. The doctor decides whether to refer the patient, and later records the result. Decisions and results can be corrected, with a reason; the original is kept.
5. Doctors can see a **statistics report** (diagnoses by age, by month, by BMI, signs found, and patients still waiting for results).

Patients, visits, vital signs, notes and staff logins come from the EMR through an **EMR adapter**. Chitawira uses an EMR called Medix; until it can be connected, a simulated Medix is used. The screening module keeps only its own data: confirmed symptoms, alerts and corrections.

## Built with

Laravel 13 (PHP 8.3+), SQLite, MySQL or PostgreSQL, Blade, Tailwind CSS and Alpine.js, and PHPUnit (393 automated tests). The online version runs on Render with a Neon PostgreSQL database.

## For developers

```
git clone https://github.com/Veeraine/pmos-screening.git
cd pmos-screening
composer install
cp .env.example .env
php artisan key:generate
php -r "touch('database/database.sqlite');"
php artisan migrate --seed
php artisan pmos:sample-report-data
npm install
npm run build
php artisan serve
```

Run the tests with `php artisan test`. Measure symptom detection accuracy with `php artisan pmos:evaluate-detection`.

## Project structure

| Folder | What is in it |
|---|---|
| `app/Screening` | Symptom detection, the Rotterdam screening check and alert rules |
| `app/Emr` | The EMR adapter, and the simulated Medix in `SimulatedMedix/` |
| `app/Reports` | The statistics report |
| `app/Http` | Controllers (screens), form checks and role checks |
| `app/Models` | Database tables |
| `config/screening.php` | Symptom phrases, Rotterdam signs and the alert threshold |
| `config/emr.php` | Which EMR adapter to use |
| `database` | Table definitions, demo data and the detection test notes |
| `resources/views` | Screen templates |
| `tests` | Automated tests |
| `start-pmos.bat` | One-click setup and start on Windows |
| `Dockerfile`, `docker/` | Settings for the online (hosted) version |

To connect the real Medix, write a class that implements `App\Emr\EmrAdapter` and select it in `config/emr.php`. Nothing in the screening code needs to change.
