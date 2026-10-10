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

    // Engagement. XP is worked out from question_attempts, so the same answers earn the same XP on web and phone.
    'xp' => [
        'correct' => 10,
        'wrong' => 2,                 // trying still counts
        'daily_answer_cap' => 400,    // XP from answers per day, so repeating one question cannot farm levels
        'challenge_bonus' => 100,
    ],
    'challenge_size' => 10,
    'challenge_min' => 5,             // a subject with fewer usable questions than this is skipped

    // Level starts at 1 with the first title; each row is the XP needed to reach it.
    'levels' => [
        ['xp' => 0, 'title' => 'Beginner'],
        ['xp' => 100, 'title' => 'Learner'],
        ['xp' => 400, 'title' => 'Rising Scholar'],
        ['xp' => 1000, 'title' => 'Scholar'],
        ['xp' => 2000, 'title' => 'Advanced Scholar'],
        ['xp' => 4000, 'title' => 'Expert'],
        ['xp' => 8000, 'title' => 'Master'],
    ],

    // The retired school screens. Off: they answer 404. Turn on only to look at old data, then turn off again.
    'legacy_school' => (bool) env('LEGACY_SCHOOL', false),

    // Rebuild a subject's downloadable pack by itself whenever its published papers change. Tests turn this off.
    'auto_packs' => (bool) env('TESTACBT_AUTO_PACKS', true),

    // Rankings. A student who joins is shown by nickname, picture and (if they choose) school, never by their real name.
    // Change privacy_version whenever the policy changes in a way that needs fresh consent: everyone is asked again.
    'privacy_version' => '2026-10',
    'avatars' => [
        'owl' => "\u{1F989}", 'fox' => "\u{1F98A}", 'lion' => "\u{1F981}", 'eagle' => "\u{1F985}", 'tiger' => "\u{1F42F}", 'panda' => "\u{1F43C}",
        'rocket' => "\u{1F680}", 'star' => "\u{2B50}", 'bolt' => "\u{26A1}", 'book' => "\u{1F4DA}", 'brain' => "\u{1F9E0}", 'trophy' => "\u{1F3C6}",
    ],
    // Words a nickname may not contain: staff and brand names (so nobody can pretend to be us) and rude words.
    'reserved_nicknames' => ['admin', 'moderator', 'support', 'staff', 'official', 'testacbt', 'helpdesk', 'fuck', 'shit', 'bitch', 'pussy', 'dick', 'nigg', 'rape', 'porn'],
    'leaderboard_size' => 50,
    'school_board_min_members' => 3,   // a school appears only once this many students from it have played, so no one is singled out
];
