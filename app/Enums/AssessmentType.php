<?php

namespace App\Enums;

enum AssessmentType: string
{
    case Quiz = 'quiz';
    case Test = 'test';
    case Exam = 'exam';
    case Project = 'project';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
