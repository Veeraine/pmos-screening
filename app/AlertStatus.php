<?php

namespace App;

/**
 * An alert is open until the doctor records whether the patient was referred.
 */
enum AlertStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
