<?php

/*
 | Exam formats used by Mock exam mode. Confirm against the exam bodies before each exam season.
 |
 | JAMB UTME (CBT): Use of English 60 + three other subjects 40 each = 180 questions, 2 hours,
 |   four options (A-D), each subject scaled to 100 for a total out of 400.
 | WAEC / NECO objective papers: normally 50 questions in about an hour; the exact time varies by
 |   subject, so a paper's own duration_minutes overrides the default below when it has one.
 */
return [

    'mock' => [
        'jamb' => [
            'label' => 'JAMB UTME',
            'subject_count' => 4,
            'compulsory' => 'english-language',
            'questions' => ['english-language' => 60, 'default' => 40],
            'minutes' => 120,
            'score_max' => 400,
        ],
        'waec' => ['label' => 'WAEC objective', 'subject_count' => 1, 'questions' => ['default' => 50], 'minutes' => 60, 'score_max' => 100],
        'neco' => ['label' => 'NECO objective', 'subject_count' => 1, 'questions' => ['default' => 50], 'minutes' => 60, 'score_max' => 100],
        // Post-UTME differs by university (many use 40 questions in 30 to 60 minutes); IGCSE multiple-choice papers are
        // usually 40 questions in 45 minutes. These are sensible defaults: confirm against the schools you cover.
        'post-utme' => ['label' => 'Post-UTME', 'subject_count' => 1, 'questions' => ['default' => 40], 'minutes' => 45, 'score_max' => 100],
        'igcse' => ['label' => 'IGCSE multiple choice', 'subject_count' => 1, 'questions' => ['default' => 40], 'minutes' => 45, 'score_max' => 100],
    ],

    // News and article categories. `path` is the public list page a category belongs to.
    'post_categories' => [
        'news' => ['label' => 'News', 'path' => 'news'],
        'exam-news' => ['label' => 'Exam news', 'path' => 'news'],
        'results' => ['label' => 'Results', 'path' => 'news'],
        'scholarship' => ['label' => 'Scholarship', 'path' => 'scholarships'],
        'blog' => ['label' => 'Blog', 'path' => 'blog'],
    ],

    'event_kinds' => [
        'exam' => 'Exam',
        'registration' => 'Registration',
        'results' => 'Results release',
        'webinar' => 'Webinar',
        'other' => 'Other',
    ],

    // Number-of-questions choices offered in Practice mode.
    'practice_counts' => [10, 20, 40, 50],

    // A subject at or above this accuracy counts as "strong"; below it, "needs work".
    'strong_accuracy' => 70,
];
