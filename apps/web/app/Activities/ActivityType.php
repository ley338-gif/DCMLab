<?php

namespace App\Activities;

/**
 * Die heute bekannten Aktivitaetstypen (ADR 0072). Kein geschlossenes
 * Aufzaehlungs-Konzept im Sinn von "das sind alle, die es je geben wird" --
 * ein sechster Typ registriert sich einfach zusaetzlich bei der
 * ActivityRegistry mit einem eigenen String-Wert, ohne dass dieses Enum
 * angefasst werden muss (siehe ActivityRegistryTest fuer den Nachweis).
 */
enum ActivityType: string
{
    case Lesson = 'lesson';
    case Quiz = 'quiz';
    case Exam = 'exam';
    case Node = 'node';
    case Sandbox = 'sandbox';
    case Achievement = 'achievement';
    case Lab = 'lab';
}
