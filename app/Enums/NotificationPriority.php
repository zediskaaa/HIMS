<?php

namespace App\Enums;

enum NotificationPriority: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}
