# Email — T-1d

**Audience:** every user with a non-null `users.last_login` in the
prior 90 days.

**Sender identity:** "MyBible <no-reply@mybible.eu>" (do NOT use a
personal name — this is a system notice).

**Scheduled-send time:** T-1d at **18:00 Europe/Bucharest** so users
read it during the evening routine, not at night.

---

## Romanian (primary)

**Subject:** Ne actualizăm sistemele — va trebui să te autentifici din nou mâine

**Body:**

Bună ziua,

Mâine, între **03:00 și 05:00 (ora României)**, facem o actualizare
importantă a sistemelor MyBible. După această actualizare:

- Vei fi **delogat(ă) automat** din aplicație.
- Va trebui să te **autentifici din nou** cu email-ul și parola obișnuite.
- **Datele tale sunt în siguranță** — notițele, favoritele, planurile de
  citire și toate însemnările rămân exact așa cum le-ai lăsat.

Dacă ți-ai uitat parola, poți folosi opțiunea "Am uitat parola" din
ecranul de autentificare.

Îți mulțumim pentru răbdare. Această actualizare ne ajută să îți oferim
o aplicație mai rapidă și mai stabilă.

Cu drag,
Echipa MyBible

---

## English (fallback for non-RO locales)

**Subject:** System update — you'll need to sign in again tomorrow

**Body:**

Hi,

Tomorrow, between **03:00 and 05:00 Europe/Bucharest time**, we're
rolling out an important update to MyBible. After the update:

- You'll be **signed out automatically** from the app.
- You'll need to **sign in again** with your usual email and password.
- **Your data is safe** — notes, favourites, reading plans, and
  everything else stay exactly as you left them.

If you've forgotten your password, use "Forgot password" on the
sign-in screen.

Thank you for bearing with us — this update makes MyBible faster and
more reliable.

Warmly,
The MyBible team

---

## QA notes

- Render both locales in the email-sender's preview.
- Send a test to `ops+smoke@mybible.eu` 24h before the blast.
- Confirm the "Forgot password" deep link opens the mobile app (not
  only the web site) on iOS and Android.
