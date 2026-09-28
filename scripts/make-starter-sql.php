<?php

/*
 * Builds database/starter-content.sql: starter news, exam news, scholarships, blog articles, events and videos.
 *   php scripts/make-starter-sql.php
 *
 * Everything is written in our own words from facts checked against news reports and official pages on 27 September 2026,
 * with the source linked in each article. Cover pictures are hotlinked from Wikimedia Commons (free licences), and each
 * article carries the photo credit those licences ask for. Running the SQL twice does not duplicate anything.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\VideoLink;

$sql = fn (?string $v) => $v === null ? 'NULL' : "'" . str_replace(['\\', "'"], ['\\\\', "''"], $v) . "'";
$link = fn (string $url, string $text) => '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
$slug = fn (string $t) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), '-');

// ---- pictures (Wikimedia Commons) ----
$T = 'https://upload.wikimedia.org/wikipedia/commons/thumb/';
$pictures = [
    'desk' => [$T . '7/7b/Desk_with_notebook_pens_and_glasses.jpg/1280px-Desk_with_notebook_pens_and_glasses.jpg', 'Desk with notebook pens and glasses', 'Shixart1985', 'CC BY 2.0', 'https://commons.wikimedia.org/wiki/File:Desk_with_notebook_pens_and_glasses.jpg'],
    'school' => ['https://upload.wikimedia.org/wikipedia/commons/1/1c/Handmaids_Girls_Secondary_School_Building.jpg', 'Handmaids Girls Secondary School Building', 'Obuezie', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:Handmaids_Girls_Secondary_School_Building.jpg'],
    'gate' => [$T . '1/18/University_of_Ibadan_gate%2C_Ibadan4.jpg/1280px-University_of_Ibadan_gate%2C_Ibadan4.jpg', 'University of Ibadan gate', 'Eukoha', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:University_of_Ibadan_gate,_Ibadan4.jpg'],
    'hall' => ['https://upload.wikimedia.org/wikipedia/commons/8/88/Trenchard_Hall%2C_University_of_Ibadan%2C_Nigeria.jpg', 'Trenchard Hall, University of Ibadan', 'Solomontosin3084', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:Trenchard_Hall,_University_of_Ibadan,_Nigeria.jpg'],
    'office' => [$T . 'b/b4/University_of_Ibadan%28VC%27s_office%29.jpg/1280px-University_of_Ibadan%28VC%27s_office%29.jpg', "University of Ibadan (VC's office)", 'African Joker', 'CC BY-SA 4.0', "https://commons.wikimedia.org/wiki/File:University_of_Ibadan(VC's_office).jpg"],
    'second' => [$T . 'e/ef/Second_gate_of_the_prestigious_University_of_Ibadan_2.jpg/1280px-Second_gate_of_the_prestigious_University_of_Ibadan_2.jpg', 'Second gate of the University of Ibadan', 'Official alade', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:Second_gate_of_the_prestigious_University_of_Ibadan_2.jpg'],
    'faculty' => [$T . '4/4f/FACULTY_OF_EDUCATION%2C_UNIVERSITY_OF_IBADAN%2C_IBADAN%2C_NIGERIA.jpg/1280px-FACULTY_OF_EDUCATION%2C_UNIVERSITY_OF_IBADAN%2C_IBADAN%2C_NIGERIA.jpg', 'Faculty of Education, University of Ibadan', 'Temilolub.52021', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:FACULTY_OF_EDUCATION,_UNIVERSITY_OF_IBADAN,_IBADAN,_NIGERIA.jpg'],
    'lutheran' => [$T . '9/9f/Office_building_of_the_Principal%2C_Lutheran_High_School%2C_Obot_Idim.jpg/1280px-Office_building_of_the_Principal%2C_Lutheran_High_School%2C_Obot_Idim.jpg', 'Office building of the Principal, Lutheran High School, Obot Idim', 'Funman19', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:Office_building_of_the_Principal,_Lutheran_High_School,_Obot_Idim.jpg'],
];
$credit = function (string $key) use ($pictures, $link) {
    [, $title, $artist, $licence, $page] = $pictures[$key];

    return '<p><small>Cover photo: &ldquo;' . htmlspecialchars($title, ENT_QUOTES) . '&rdquo; by ' . htmlspecialchars($artist, ENT_QUOTES) . ', ' . $link($page, $licence) . ', via Wikimedia Commons.</small></p>';
};
$sources = fn (array $items) => '<p><small>Sources: ' . implode('; ', array_map(fn ($i) => $link($i[0], $i[1]), $items)) . '. Checked 27 September 2026. Always confirm details on the official website before you act.</small></p>';
$ul = fn (array $items) => '<ul>' . implode('', array_map(fn ($i) => "<li>{$i}</li>", $items)) . '</ul>';

// ---- articles ----
// [category, title, excerpt, body, picture, exam slug|null, deadline, source, link, days ago, featured]
$posts = [];

$posts[] = ['results', 'JAMB 2026 UTME results: how the scores were spread, and what to do with yours',
    'About one in five candidates scored 200 or more in the 2026 UTME. Here is how the scores were spread and how to plan from yours.',
    '<p>After JAMB released the 2026 UTME results in April, analyses of the figures showed that 1,955,069 candidates sat the exam, with 1,897,692 results out at the time they were compiled.</p>'
    . '<h2>How the scores were spread</h2>'
    . $ul(['<strong>200 and above:</strong> 420,415 candidates, about 21.5%', '<strong>250 and above:</strong> 85,855 candidates, about 4.5%', '<strong>300 and above:</strong> 12,414 candidates, about 0.6%', 'More than half of all candidates scored between 160 and 199'])
    . '<h2>What this means for you</h2>'
    . '<p>A score is one part of admission, not the whole of it. Each school and course sets its own bar, and competitive courses such as Medicine and Law sit far above the national minimum. Find the cut-off your target school used last year, then work out the gap between that and your score.</p>'
    . '<p>If you are preparing for the next UTME, the numbers above are a useful target: a score above 250 puts you in roughly the top 5%. The most reliable way there is steady practice in the real format. Try a timed <a href="/mock">mock exam</a> and review every question you missed.</p>'
    . $sources([['https://afronews.ng/2026/04/23/breakdown-analysis-of-2026-jamb-results/', 'Afronews: breakdown analysis of the 2026 JAMB results']]) . $credit('gate'),
    'gate', 'jamb', null, 'Afronews.ng', 'https://afronews.ng/2026/04/23/breakdown-analysis-of-2026-jamb-results/', 6, 0];

$posts[] = ['exam-news', 'JAMB 2026 policy meeting: 150 cut-off for universities, and who no longer writes UTME',
    'JAMB kept the minimum university cut-off at 150, set 100 for polytechnics, and exempted NCE and some agriculture candidates from the UTME.',
    '<p>At its annual policy meeting on 11 May 2026 in Abuja, chaired by the Minister of Education, Dr Tunji Alausa, JAMB and the institutions fixed the minimum scores for the 2026 admission exercise.</p>'
    . '<h2>Minimum cut-off marks</h2>'
    . $ul(['<strong>Universities:</strong> 150', '<strong>Colleges of nursing sciences:</strong> 150', '<strong>Polytechnics and monotechnics:</strong> 100'])
    . '<h2>Who no longer needs to write UTME</h2>'
    . '<p>Candidates seeking admission into the Nigeria Certificate in Education (NCE) with at least four credit passes no longer need to sit the UTME. Reports say the same applies to National Diploma programmes in non-technology agriculture and related courses. The policy takes effect from the 2026/2027 admission exercise.</p>'
    . '<h2>Remember</h2>'
    . '<p>These are minimums. Many universities and courses admit only candidates who score well above them, so a score of 150 does not guarantee a place. Admission deadlines were also announced, but reports give slightly different dates, so read the notice on JAMB&rsquo;s official website for the exact ones.</p>'
    . $sources([['https://guardian.ng/news/jamb-retains-150-cut-off-mark-for-varsities-exempts-nce-candidates-from-utme/', 'The Guardian'], ['https://www.vanguardngr.com/2026/05/breaking-jamb-sets-150-as-cut-off-mark-for-university-admissions/', 'Vanguard'], ['https://saharareporters.com/2026/05/11/jamb-sets-150-cut-mark-universities-nursing-colleges-100-polytechnics', 'Sahara Reporters'], ['https://jamb.gov.ng/', 'JAMB']]) . $credit('office'),
    'office', 'jamb', null, 'The Guardian, Vanguard and Sahara Reporters', 'https://www.vanguardngr.com/2026/05/breaking-jamb-sets-150-as-cut-off-mark-for-university-admissions/', 12, 0];

$posts[] = ['exam-news', 'JAMB to add live facial verification to the 2027 UTME',
    'JAMB says candidates will be checked by live facial recognition, with NIMC confirming their identity, to fight impersonation.',
    '<p>JAMB has announced that candidates sitting the 2027 UTME will go through facial biometric verification, as part of its effort to stop impersonation and other forms of exam malpractice.</p>'
    . '<h2>How it will work, as announced</h2>'
    . $ul(['Candidates take a live facial verification before they are allowed to sit the exam.', 'The result goes to the National Identity Management Commission (NIMC), which returns a code to JAMB confirming or rejecting the candidate&rsquo;s identity.', 'The existing 10-fingerprint check stays alongside the facial check at first.', 'JAMB says fingerprint checks will be phased out over the next two to three years.'])
    . '<h2>What you can do now</h2>'
    . '<p>Make sure your National Identification Number (NIN) is correct and matches the name on your school records, and register with your own details. Mismatches are the most common cause of delays. Keep checking JAMB&rsquo;s official website for the registration dates and full instructions.</p>'
    . $sources([['https://leadership.ng/jamb-introduces-facial-scan-to-curb-utme-impersonation/', 'Leadership'], ['https://www.legit.ng/education/1728333-jamb-introduces-facial-verification-utme-reason/', 'Legit.ng'], ['https://thesun.ng/jamb-introduces-facial-scan-to-phase-out-fingerprint-for-utme/', 'The Sun']]) . $credit('hall'),
    'hall', 'jamb', null, 'Leadership, Legit.ng and The Sun', 'https://leadership.ng/jamb-introduces-facial-scan-to-curb-utme-impersonation/', 20, 0];

$posts[] = ['results', 'WAEC 2026 WASSCE results are out for school candidates: how to check yours',
    'WAEC has released the 2026 WASSCE results for school candidates. Here are the steps to check yours, and how to avoid scams.',
    '<p>The West African Examinations Council (WAEC) has released the 2026 WASSCE results for school candidates. About 1.96 million candidates from more than 24,000 schools took part, in an examination that ran from 21 April to 19 June 2026.</p>'
    . '<h2>How to check your result</h2>'
    . '<ol><li>Generate a result-checking PIN on WAEC&rsquo;s official website, <strong>waec.org</strong>.</li><li>Go to the result portal, <strong>waecdirect.org</strong>.</li><li>Enter your PIN, your examination number and your National Identification Number (NIN).</li><li>Confirm with the one-time password (OTP) that is sent to you.</li></ol>'
    . '<h2>Stay safe</h2>'
    . '<p>Use only WAEC&rsquo;s own websites. Do not give your PIN or NIN to anyone, and do not pay an agent or an unofficial website to &ldquo;check&rdquo; or &ldquo;fix&rdquo; a result. If a result looks wrong, follow the complaint process on the official portal.</p>'
    . '<h2>What next</h2>'
    . '<p>Most universities need at least five credits, including English Language and Mathematics, in one sitting. If you need to improve a grade, start early: practise the subject by year and topic on TestaCBT and take a timed mock.</p>'
    . $sources([['https://www.legit.ng/education/1723281-breaking-waec-releases-results-2026-wassce-exercise/', 'Legit.ng'], ['https://leadership.ng/waec-releases-2026-results-for-candidates/', 'Leadership'], ['https://www.waecdirect.org/', 'WAEC Direct']]) . $credit('school'),
    'school', 'waec', null, 'WAEC, via Legit.ng and Leadership', 'https://www.waecdirect.org/', 3, 0];

$posts[] = ['exam-news', 'Cambridge IGCSE: when results come out, and how to use the weeks before the exams',
    'IGCSE results follow a familiar calendar. Here is when to expect them and how to prepare for the October and November series.',
    '<p>Cambridge IGCSE results follow a predictable calendar, and knowing it helps you plan.</p>'
    . $ul(['<strong>June series:</strong> the 2026 IGCSE results were issued on Tuesday 18 August 2026.', '<strong>November series:</strong> results are traditionally issued in mid-January. For the November 2025 series, IGCSE results came out on Thursday 15 January 2026.', '<strong>The October/November 2026 series</strong> starts in late September and runs to mid-November.'])
    . '<h2>Making the most of your revision</h2>'
    . '<p>Work through past papers by topic first, then under timed conditions. After each paper, list the questions you missed and the reason: a knowledge gap, a careless slip, or running out of time. Fix the gaps first, because they are the easiest marks to win back.</p>'
    . '<p>You can practise IGCSE multiple-choice questions on TestaCBT, one subject at a time, and try a timed mock when you feel ready. Exact dates and rules for your school can differ, so confirm them with your exams officer.</p>'
    . $sources([['https://www.tes.com/magazine/analysis/secondary/when-cambridge-international-igcse-a-level-exam-results-days', 'Tes: when are Cambridge IGCSE and A-level results days'], ['https://www.cambridgeinternational.org/', 'Cambridge International']]) . $credit('lutheran'),
    'lutheran', 'igcse', null, 'Tes', 'https://www.tes.com/magazine/analysis/secondary/when-cambridge-international-igcse-a-level-exam-results-days', 30, 0];

$posts[] = ['news', 'Welcome to TestaCBT: free questions, timed mocks and exam news in one place',
    'Practise past questions for free, take timed mock exams, and keep up with exam news, scholarships and events.',
    '<p>TestaCBT helps you prepare for your exams with real past questions, simple explanations and timed mock exams. Here is how to get the most from it.</p>'
    . '<h2>Start free</h2>'
    . '<p>Every subject has free questions, so you can try before you pay. Pick your exam, choose a subject and a year, and see the right answer straight away, with an explanation in English or Pidgin.</p>'
    . '<h2>Practise like the real thing</h2>'
    . '<p>Take a timed mock exam to build speed. The JAMB mock follows the CBT format: Use of English plus three subjects, 180 questions in two hours.</p>'
    . '<h2>Know what to work on</h2>'
    . '<p>Your progress page shows your score for each subject and your study streak, so you can spend your time where it counts.</p>'
    . '<h2>Stay informed</h2>'
    . '<p>Follow the news, scholarships, events and videos on this site, and share anything useful with a friend using the share buttons.</p>'
    . $credit('desk'),
    'desk', null, null, null, null, 1, 1];

// Scholarships: same shape, with a deadline and the organisation offering it.
$posts[] = ['scholarship', 'Chevening Scholarships 2027/28: applications close on 6 October 2026',
    'The UK government-funded Chevening scholarship for master&rsquo;s study closes on 6 October 2026 at 11:00 UTC.',
    '<p>Chevening is the UK government&rsquo;s international scholarship programme, offering funded postgraduate study in the UK. Applications for the 2027/28 cycle are open now and close on <strong>6 October 2026 at 11:00 UTC</strong>.</p>'
    . '<h2>Key dates</h2>'
    . $ul(['<strong>4 August 2026:</strong> applications opened', '<strong>6 October 2026:</strong> applications close', '<strong>Mid-February 2027:</strong> shortlisting decisions and interview invitations', '<strong>March to April 2027:</strong> interviews at British embassies and high commissions', '<strong>Mid-June 2027:</strong> interview results', '<strong>September or October 2027:</strong> scholars begin their studies'])
    . '<h2>Who it is for</h2>'
    . '<p>Chevening is for graduates, not school leavers. Applicants normally need an undergraduate degree, several years of work experience and a commitment to return to Nigeria for at least two years after the scholarship. The conditions change from year to year, so read the official Nigeria page carefully before you apply.</p>'
    . '<p>Still in secondary school? Keep this on your list for after your first degree, and keep your grades strong now.</p>'
    . $sources([['https://www.chevening.org/scholarships/application-timeline/', 'Chevening application timeline'], ['https://www.chevening.org/scholarship/nigeria/', 'Chevening in Nigeria']]) . $credit('second'),
    'second', null, '2026-10-06', 'UK Government (Chevening)', 'https://www.chevening.org/scholarship/nigeria/', 5, 0];

$posts[] = ['scholarship', 'Commonwealth Scholarships 2027/28 for Nigerians: apply by 20 October 2026',
    'Nigerian graduates can apply for UK Commonwealth Master&rsquo;s and PhD scholarships through the Federal Scholarship Board until 20 October 2026.',
    '<p>Nigerian graduates can apply for the 2027/28 UK Commonwealth Scholarships for a one-year taught Master&rsquo;s or for doctoral study of up to three years at eligible UK universities.</p>'
    . $ul(['<strong>Deadline:</strong> 20 October 2026, 4:00 pm British time', '<strong>How to apply:</strong> through the Federal Scholarship Board, the national nominating agency for Nigeria', '<strong>Master&rsquo;s applicants:</strong> a first degree with at least a Second Class Upper Division', '<strong>Questions:</strong> fsb@education.gov.ng'])
    . '<p>Check the official advert for the full list of documents and steps. Apply only through the official channels: the scholarship is free to apply for, and no one can sell you a place.</p>'
    . $sources([['https://leadership.ng/how-nigerian-graduates-can-apply-for-2027-2028-uk-commonwealth-scholarships/', 'Leadership'], ['https://education.gov.ng/', 'Federal Ministry of Education']]) . $credit('faculty'),
    'faculty', null, '2026-10-20', 'Commonwealth Scholarship Commission (UK) and the Federal Scholarship Board', 'https://leadership.ng/how-nigerian-graduates-can-apply-for-2027-2028-uk-commonwealth-scholarships/', 8, 0];

$posts[] = ['scholarship', 'Federal Government BEA scholarships 2026/27: what to know before you apply',
    'The Federal Scholarship Board offers awards to study abroad under bilateral education agreements. The closing date had not been announced at the time of writing.',
    '<p>The Federal Scholarship Board offers Bilateral Education Agreement (BEA) awards for Nigerians to study in partner countries. The 2026/27 invitation is out, but reports say the closing date had <strong>not yet been announced</strong>, so keep checking the official portal.</p>'
    . '<h2>What has been reported</h2>'
    . $ul(['Undergraduate awards have been tenable in countries such as Russia, Morocco, Hungary, Egypt and Algeria.', 'Undergraduate applicants have been asked for seven distinctions (A1 to B3) in WASSCE (May/June) in subjects relevant to their course, including English Language and Mathematics.', 'The age range for undergraduate applicants has been 17 to 27.'])
    . '<p>Countries and conditions change every year, so treat this as a guide and read the official notice. This is the reason to aim high in WAEC: strong grades keep doors like this open.</p>'
    . '<h2>Avoid the agents</h2>'
    . '<p>Applying is free. Never pay an agent, and never share your passwords. Use only the Federal Ministry of Education website.</p>'
    . $sources([['https://education.gov.ng/', 'Federal Ministry of Education'], ['https://www.opportunitiesforafricans.com/bilateral-education-agreement-bea-scholarship-awards-2025-2026/', 'Opportunities for Africans: BEA guide']]) . $credit('lutheran'),
    'lutheran', null, null, 'Federal Scholarship Board', 'https://education.gov.ng/', 15, 0];

$posts[] = ['scholarship', 'NLNG Undergraduate Scholarship: get your documents ready before the next window opens',
    'Nigeria LNG pays &#8358;300,000 a year to selected first-year students. The next window has not been announced, so prepare now.',
    '<p>The Nigeria LNG (NLNG) Undergraduate Scholarship Scheme is aimed at first-year students in federal and state universities. The next application window has not been announced. The last one ran from 17 November to 12 December 2025, so it is worth preparing early.</p>'
    . '<h2>What the last round asked for</h2>'
    . $ul(['Nigerian citizen living in Nigeria', 'Full-time first-year undergraduate at a federal or state university', 'At least five O&rsquo;Level credits, including English and Mathematics, in one sitting (WASSCE or NECO)', 'A UTME score of at least 200', 'Aged 18 to 25, and not on another scholarship or bursary'])
    . '<p>The award was &#8358;300,000 a year. Rules and dates can change, so read the notice on the official NLNG website when the next round opens.</p>'
    . '<h2>Get ready now</h2>'
    . '<p>Keep your result slips, UTME result and admission letter safely scanned. Score well in your O&rsquo;Level and UTME, and you will already meet the main conditions.</p>'
    . $sources([['https://opportunitydesk.org/2025/11/18/nlng-undergraduate-scholarship-scheme-2026/', 'Opportunity Desk'], ['https://www.nlng.com/', 'Nigeria LNG']]) . $credit('gate'),
    'gate', null, null, 'Nigeria LNG Limited', 'https://www.nlng.com/', 25, 0];

// Blog: evergreen advice written for TestaCBT.
$posts[] = ['blog', 'An 8-week study plan for the JAMB CBT',
    'A simple plan for the two months before your UTME: learn, practise, then simulate the real exam.',
    '<p>The JAMB UTME is a computer-based test of 180 questions in two hours: Use of English (60) plus three subjects (40 each), scored out of 400. That gives you about 40 seconds a question, so speed matters as much as knowledge. Here is a plan that builds both.</p>'
    . '<h2>Weeks 1 and 2: find your gaps</h2>'
    . '<p>Get the official syllabus for each subject. Take one short practice set in every subject and note the topics you got wrong. That list is your study plan.</p>'
    . '<h2>Weeks 3 to 6: learn, then practise</h2>'
    . '<p>Give each subject a few days at a time. Study a topic, then answer past questions on it the same day. Keep an error notebook: write the question, the right answer and why you missed it.</p>'
    . '<h2>Weeks 7 and 8: simulate the exam</h2>'
    . '<p>Take at least two full timed mocks, one with your exact subject combination. Review every wrong answer, and practise finishing with time to check your work.</p>'
    . '<h2>Every day</h2>'
    . $ul(['Study at the same time each day', 'Sleep properly: tired minds forget', 'Do a few Use of English questions every day, since it is worth the most marks'])
    . '<p>Start today with a free practice set on TestaCBT, then build up to a full mock.</p>' . $credit('desk'),
    'desk', 'jamb', null, null, null, 2, 0];

$posts[] = ['blog', 'How to use past questions properly (and not just memorise answers)',
    'Past questions are the best way to prepare, if you use them to learn how the exam thinks, not to memorise letters.',
    '<p>Past questions show you what examiners ask and how they ask it. Used well, they are the most powerful revision tool you have. Used badly, they become a memory test that does not help on exam day.</p>'
    . '<h2>1. Learn the topic first</h2>'
    . '<p>Try questions after you have studied a topic, then check what you missed. Doing them cold is fine for a diagnosis, but not for learning.</p>'
    . '<h2>2. Read the explanation, even when you were right</h2>'
    . '<p>A correct guess teaches you nothing. Read why the right answer is right, and why the others are wrong.</p>'
    . '<h2>3. Practise by year and by topic</h2>'
    . '<p>One year at a time shows you a full paper the way it appears. One topic at a time repairs your weak spots. Use both.</p>'
    . '<h2>4. Save the hard ones</h2>'
    . '<p>Bookmark questions that trap you and return to them a week later. If you can still solve them, you have learned them.</p>'
    . '<h2>5. Time yourself</h2>'
    . '<p>Once you know the material, answer under exam timing. Speed is a skill that only practice builds.</p>' . $credit('hall'),
    'hall', null, null, null, null, 4, 0];

$posts[] = ['blog', 'Stay calm and manage your time in a CBT exam',
    'Nerves and the clock cost more marks than hard questions do. These habits help.',
    '<p>In a computer-based test, the two things that most often cost marks are panic and poor timing. Both can be trained.</p>'
    . '<h2>Before the exam</h2>'
    . $ul(['Visit the centre in advance if you can, so the place is not new on the day', 'Practise on a computer, with a mouse, under a timer', 'Sleep well the night before and eat something light'])
    . '<h2>During the exam</h2>'
    . $ul(['Work out your time per question. In a 180-question, two-hour paper it is about 40 seconds.', 'Do the questions you know first. Skip the ones that stall you, and flag them to come back to.', 'Never leave a question blank: there is no penalty for a wrong answer in most objective papers, so make your best guess.', 'Watch the clock at set points, for example every 30 questions.'])
    . '<h2>If you feel panic rising</h2>'
    . '<p>Stop, take three slow breaths, and read the question again. One question is not worth losing the next ten.</p>' . $credit('second'),
    'second', null, null, null, null, 7, 0];

$posts[] = ['blog', 'WAEC, NECO, JAMB, Post-UTME and IGCSE: what is each exam for?',
    'Five exams, five different jobs. Here is what each one is and when you take it.',
    '<p>Students often hear these names together, but they do different things.</p>'
    . $ul(['<strong>WAEC (WASSCE) and NECO (SSCE)</strong> are school-leaving certificate exams taken at the end of secondary school. Universities and employers look at your grades, and most tertiary courses ask for credits in English and Mathematics.', '<strong>JAMB (UTME)</strong> is the entrance exam for universities, polytechnics and colleges. Your score decides whether you meet a school&rsquo;s cut-off.', '<strong>Post-UTME</strong> is a screening a school may run after UTME, to choose among candidates who met the cut-off. Each school sets its own format.', '<strong>IGCSE</strong> is the Cambridge international secondary qualification, taken in schools that follow that programme, in June and in October or November.'])
    . '<h2>Which do you need?</h2>'
    . '<p>Most students who want a Nigerian university place need a WAEC or NECO result with the right credits, plus the UTME, plus a Post-UTME if the school asks for one. Check the requirements of your chosen course early, so you know your targets.</p>' . $credit('faculty'),
    'faculty', null, null, null, null, 9, 0];

$posts[] = ['blog', 'How to spot a scholarship scam',
    'Real scholarships never ask you to pay. Here are the warning signs.',
    '<p>Scholarship scams cost students money and personal information every year. A few simple checks will protect you.</p>'
    . $ul(['<strong>You are asked to pay.</strong> Genuine scholarships do not charge application, processing or &ldquo;unlock&rdquo; fees.', '<strong>The website looks official but the address is odd.</strong> Type the organisation&rsquo;s known address yourself, or find the link from its official page.', '<strong>You are pressured to act at once.</strong> Real programmes publish clear dates and give you time.', '<strong>They ask for your password, PIN or bank details.</strong> No scholarship needs these to consider your application.', '<strong>You did not apply.</strong> Be careful with messages that say you have already won.'])
    . '<p>Before you apply, find the same scholarship on the official website of the organisation that offers it, and read the full rules there. If in doubt, ask your school or a teacher.</p>' . $credit('school'),
    'school', null, null, null, null, 11, 0];

// ---- events ----
$events = [
    ['Chevening scholarship applications close', 'registration', '2026-10-06', null, 'Online', 'https://www.chevening.org/scholarship/nigeria/', 'Closing time is 11:00 UTC. Graduates only.'],
    ['Commonwealth Scholarships applications close', 'registration', '2026-10-20', null, 'Through the Federal Scholarship Board', 'https://leadership.ng/how-nigerian-graduates-can-apply-for-2027-2028-uk-commonwealth-scholarships/', 'Closing time is 4:00 pm British time. For Master\'s and PhD study in the UK.'],
];

// ---- videos (all links checked to exist and allow embedding) ----
$videos = [
    ['https://www.youtube.com/watch?v=ApovrgoJl6s', 'Practical tips on how to score above 300 in JAMB (UTME)', 'jamb', 'Study and exam tips for JAMB UTME candidates, from the channel of Benita Eoma on YouTube.', 1],
    ['https://www.youtube.com/watch?v=lwB7pE0PLUw', 'How you should prepare for your exams as a JAMB or WAEC student', 'jamb', 'Exam preparation advice for JAMB and WAEC students, from the mathphytutor channel on YouTube.', 0],
    ['https://www.youtube.com/watch?v=IFJLne1U7_w', 'How to prepare and pass WAEC in one sitting', 'waec', 'Tips for preparing for the WAEC examination, from the Studentship channel on YouTube.', 0],
    ['https://www.youtube.com/watch?v=3ah61NKsCpc', 'WAEC Maths 2026: how to score an A1, step by step', 'waec', 'A guide to preparing for WAEC Mathematics, from the Exam Preparation & Study Hacks channel on YouTube.', 0],
    ['https://www.youtube.com/watch?v=lI6_W8a1TZo', 'WAEC 2026 science exam guide: essential tips to pass', 'waec', 'Tips for the WAEC science papers, from the Excellent Link Academy channel on YouTube.', 0],
    ['https://www.youtube.com/watch?v=LM7v7a6H1kA', 'WAEC Mathematics 2026: sequences and series past questions solved', 'waec', 'Worked WAEC past questions on sequences and series, from the Upvision Concepts Academy channel on YouTube.', 0],
    ['https://www.youtube.com/watch?v=CYTfp8Wjy70', 'How I got an A* in IGCSE Physics: notes, top tips, examples', 'igcse', 'Revision advice for IGCSE Physics from a student, on the Adora Yin channel on YouTube.', 0],
    ['https://www.tiktok.com/@student_lecturer/video/7506040867585674551', 'Post-UTME and university screening: what is the difference?', 'post-utme', 'A short explainer on how Post-UTME exams differ from screening, from the STUDENT LECTURER account on TikTok.', 0],
];

// ---- write the SQL ----
$out = [];
$out[] = "-- TestaCBT starter content: news, exam news, results, scholarships, blog articles, events and videos.";
$out[] = "-- Generated by scripts/make-starter-sql.php. Safe to run more than once: rows that already exist are skipped.";
$out[] = "-- Run it on the server with:  mysql -u YOUR_DB_USER -p YOUR_DB_NAME < database/starter-content.sql";
$out[] = "-- Cover pictures load from Wikimedia Commons (free licences); the photo credit is at the bottom of each article.";
$out[] = "";
$out[] = "SET NAMES utf8mb4;";
$out[] = "SET @admin := (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1);";
foreach (['jamb', 'waec', 'neco', 'igcse', 'post-utme'] as $e) { $out[] = 'SET @exam_' . str_replace('-', '_', $e) . " := (SELECT id FROM exams WHERE slug = '{$e}');"; }
$out[] = "";
$out[] = "-- ---------- Articles ----------";

foreach ($posts as [$category, $title, $excerpt, $body, $picture, $exam, $deadline, $source, $url, $daysAgo, $featured]) {
    $excerpt = html_entity_decode($excerpt, ENT_QUOTES);
    $examSql = $exam ? '@exam_' . str_replace('-', '_', $exam) : 'NULL';
    $when = "NOW() - INTERVAL {$daysAgo} DAY";
    $out[] = "INSERT IGNORE INTO posts (category, title, slug, excerpt, body, cover_path, status, is_featured, published_at, deadline, source, link_url, exam_id, author_id, created_at, updated_at) VALUES ("
        . implode(', ', [$sql($category), $sql(html_entity_decode($title, ENT_QUOTES)), $sql($slug(html_entity_decode($title, ENT_QUOTES))), $sql(mb_substr($excerpt, 0, 300)), $sql($body), $sql($pictures[$picture][0]), "'published'", $featured, $when, $deadline ? $sql($deadline) : 'NULL', $sql($source), $sql($url), $examSql, '@admin', 'NOW()', 'NOW()'])
        . ");";
}

$out[] = "";
$out[] = "-- ---------- Events ----------";
foreach ($events as [$title, $kind, $start, $end, $where, $url, $note]) {
    $out[] = "INSERT IGNORE INTO events (title, slug, kind, description, starts_on, ends_on, location, link_url, status, exam_id, created_at, updated_at) VALUES ("
        . implode(', ', [$sql($title), $sql($slug($title)), $sql($kind), $sql($note), $sql($start), $end ? $sql($end) : 'NULL', $sql($where), $sql($url), "'published'", 'NULL', 'NOW()', 'NOW()']) . ");";
}

$out[] = "";
$out[] = "-- ---------- Videos (links only; nothing is uploaded) ----------";
foreach ($videos as [$url, $title, $exam, $description, $featured]) {
    $v = VideoLink::parse($url);
    $examSql = '@exam_' . str_replace('-', '_', $exam);
    $out[] = "INSERT INTO videos (title, description, platform, external_id, url, embed_url, thumbnail_url, exam_id, is_featured, status, added_by, created_at, updated_at) SELECT "
        . implode(', ', [$sql($title), $sql($description), $sql($v['platform']), $sql($v['external_id']), $sql($v['url']), $sql($v['embed_url']), $sql($v['thumbnail_url']), $examSql, $featured, "'published'", '@admin', 'NOW()', 'NOW()'])
        . " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM videos WHERE url = " . $sql($v['url']) . ");";
}

file_put_contents(__DIR__ . '/../database/starter-content.sql', implode("\n", $out) . "\n");
echo 'wrote database/starter-content.sql: ' . count($posts) . ' articles, ' . count($events) . ' events, ' . count($videos) . " videos\n";
