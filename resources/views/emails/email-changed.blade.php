<p>Hi {{ $user->name }},</p>

<p>
    This is a notification that the email address associated with your account
    has been changed from <strong>{{ $oldEmail }}</strong> to <strong>{{ $user->email }}</strong>.
</p>

<p>
    If you did not expect this change, please contact your administrator immediately.
</p>