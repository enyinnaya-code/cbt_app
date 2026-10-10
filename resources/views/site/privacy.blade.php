@extends('layouts.public')

@section('title', 'Privacy policy')
@section('meta_description', 'What TestaCBT keeps about you, what the daily challenge rankings show, and how to leave and have it deleted.')

@php $support = \App\Models\Setting::get('support.email'); @endphp

@section('content')
<section class="section" style="padding-top:12px;max-width:760px">
    <div class="section-head"><div><h1 class="h1">Privacy policy</h1>
        <p>Version {{ config('testacbt.privacy_version') }}. In plain words: what we keep, why, and what you control.</p></div></div>

    <div class="card prose stack" style="gap:6px">
        <h2 class="h2">What we keep when you use TestaCBT</h2>
        <p>Your name and email (to sign you in and send receipts), your password (kept scrambled, so even we cannot read it), the questions you answer and your scores (so we can show your progress and suggest what to revise), and, if you buy a subject, a record of the payment. Card details are handled by Paystack and never reach us.</p>

        <h2 class="h2">The daily challenge and the rankings are optional</h2>
        <p>You do not have to join. Practice, mock exams and your own progress work the same without them. If you choose to join the daily challenge, your results are used to rank students against each other.</p>
        <p><b>What the rankings show:</b> only the nickname you choose, the picture you pick, your points, and the name of your school if you choose to type one. We never show your real name, your email or your phone.</p>
        <ul>
            <li><b>Nickname:</b> pick something that does not name you. Other students can see it. Do not use your full name or contact details.</li>
            <li><b>School (optional):</b> if you add it, your points also count toward your school on the school board, and other students can see which school you named. A school appears only when at least {{ config('testacbt.school_board_min_members') }} students from it have played, so no one is singled out.</li>
            <li><b>Who it is for:</b> the rankings are open to students who joined. They are not shown to the public website.</li>
        </ul>

        <h2 class="h2">Your choices</h2>
        <ul>
            <li>You can change your nickname, picture or school at any time from the Rankings page.</li>
            <li>You can leave at any time. We then delete your nickname, picture and school and take you off every board straight away. Your practice history stays on your account.</li>
            <li>You can ask us to delete your whole account and everything we hold about you{{ $support ? ' by writing to ' . $support : '' }}.</li>
        </ul>

        <h2 class="h2">Children</h2>
        <p>Many of our students are under 18. If you are under 18, please ask a parent or guardian before you join the rankings or choose a school. Never put anything in your nickname that tells strangers who you are or where you live.</p>

        <h2 class="h2">How we protect it</h2>
        <p>Connections to TestaCBT are encrypted. Passwords are stored scrambled. Staff can only reach student accounts to help with a problem, and an admin who resets a password signs that person out everywhere. We do not sell your information and we do not show adverts based on it.</p>

        <h2 class="h2">Changes to this policy</h2>
        <p>If we change this policy in a way that affects the rankings, you will be asked to read and accept it again before you stay on the boards.</p>

        <p class="small muted">This policy is written to follow the Nigeria Data Protection Act 2023. It is a plain-language summary and should be checked by a lawyer before launch.</p>
    </div>
</section>
@endsection
