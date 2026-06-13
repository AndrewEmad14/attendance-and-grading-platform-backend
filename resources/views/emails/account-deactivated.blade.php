<p>Hi {{ $user->name }},</p>

<p>
    This is a notification that your account on the ITI Attendance & Grading Platform
    has been deactivated as of <strong>{{ now()->format('F j, Y') }}</strong>.
</p>

<p>
    If you believe this was done in error, please contact your administrator immediately.
</p>

<p>
    Thank you for your time with us.
</p>