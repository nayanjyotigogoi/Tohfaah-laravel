@component('mail::message')
# You’ve Been Invited to Add a Memory 💖

Hi there,

**{{ $inviter->full_name ?? $inviter->name }}** has invited you to collaborate on:

### 🗺️ {{ $map->title }}

Add your own memories and moments to make this experience even more meaningful ✨

@component('mail::button', ['url' => config('app.frontend_url') . '/premium-gifts/map-memory/' . $map->id])
Open Memory Map
@endcomponent

---

If you weren’t expecting this invite, you can safely ignore this email.

With love,  
**Team Tohfaah** 💕

@endcomponent