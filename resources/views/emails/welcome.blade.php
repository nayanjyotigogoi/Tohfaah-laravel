@component('mail::message')
# Welcome to Tohfaah 💖

Hi {{ $user->name ?? 'there' }},

We’re so happy you joined us ✨  

Tohfaah is a place where **simple gestures become unforgettable memories** —
kisses 💋, hugs 🤍, flowers 🌸, and surprises made with love.

---

### 💝 Start your first moment
Send something meaningful to someone special — no apps, no complexity.

@component('mail::button', ['url' => 'https://tohfaah.com'])
Create Your First Gift
@endcomponent

---

If you need help, just reply to this email — we’re real humans 😊

With love,  
**Team Tohfaah** 💕

@endcomponent
