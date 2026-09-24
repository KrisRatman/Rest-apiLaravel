<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';

    /**
     * Название для показа в клиенте, чтобы не держать словарь на его стороне.
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To do',
            self::InProgress => 'In progress',
            self::Review => 'Review',
            self::Done => 'Done',
        };
    }
}
