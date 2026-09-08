<?php

namespace App\Enums;

enum AuthenticatorSecretStatus
{
    case Missing;
    case Valid;
    case Invalid;
}
