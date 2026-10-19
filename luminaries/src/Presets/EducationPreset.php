<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Presets;

/**
 * Optional semantic aliases for the GCEGO learning application.
 *
 * Kept apart from the provider engine and from FrameworkPreset: enable it
 * with config('luminaries.presets') => ['framework', 'education'].
 */
final class EducationPreset
{
    public const string Name = 'education';

    public const array Aliases = [
        'education.academic-level' => 'heroicon:academic-cap',
        'education.ordinary-level' => 'lucide:book-open',
        'education.advanced-level' => 'lucide:book-open-check',
        'education.subject' => 'heroicon:book-open',
        'education.topic' => 'heroicon:list-bullet',
        'education.lesson' => 'lucide:presentation',
        'education.question' => 'heroicon:question-mark-circle',
        'education.past-paper' => 'heroicon:document-text',
        'education.practice' => 'lucide:notebook-pen',
        'education.examination' => 'heroicon:clipboard-document-check',
        'education.student' => 'heroicon:user',
        'education.students' => 'heroicon:user-group',
        'education.teacher' => 'lucide:graduation-cap',
        'education.school' => 'lucide:school',
        'education.institution' => 'heroicon:building-library',

        'navigation.students' => 'education.students',
        'navigation.teachers' => 'education.teacher',
        'navigation.academics' => 'education.academic-level',

        'resources.subjects' => 'education.subject',
        'resources.topics' => 'education.topic',
        'resources.lessons' => 'education.lesson',
        'resources.questions' => 'education.question',
        'resources.examinations' => 'education.examination',
        'resources.past-papers' => 'education.past-paper',
    ];
}
