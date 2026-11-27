<?php

use App\Emr\SimulatedMedix\SimulatedMedixAdapter;

return [

    /*
    |--------------------------------------------------------------------------
    | EMR adapter
    |--------------------------------------------------------------------------
    |
    | Which EMR adapter to use (must implement App\Emr\EmrAdapter). "simulated_medix" uses our own tables.
    | For the real Medix, add its adapter below and set EMR_ADAPTER in .env.
    |
    */

    'adapter' => env('EMR_ADAPTER', 'simulated_medix'),

    'adapters' => [
        'simulated_medix' => SimulatedMedixAdapter::class,
    ],

];
