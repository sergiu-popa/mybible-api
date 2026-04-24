# Push notification — T-1d

**Audience:** all devices with push tokens registered, filtered to RO
and EN locales.

**Sender identity:** MyBible (app-level, no individual name).

**Scheduled-send time:** T-1d at **19:30 Europe/Bucharest** — 90
minutes after the email, so it acts as a reminder for users who
skipped the email.

**Character budget:** under 300 characters per locale (iOS notification
centre clips at ~250 on the lock screen; leaving headroom).

---

## Romanian (118 characters)

**Title:** Actualizare MyBible mâine

**Body:** Mâine între 03:00–05:00 facem o actualizare. Va trebui să te
autentifici din nou după aceea. Datele tale sunt în siguranță.

---

## English (111 characters)

**Title:** MyBible update tomorrow

**Body:** Tomorrow between 03:00–05:00 (EET) we're rolling out an
update. You'll need to sign in again afterward. Your data is safe.

---

## Deep-link

**Click-through target:** `mybible://auth/login`. Opens the login
screen directly when the user taps the notification — pre-loading the
login UI reduces friction at post-cutover re-auth time.

## QA notes

- Verify character counts on both iOS (Apple Push) and Android (FCM)
  previews; Cyrillic diacritics can inflate byte count vs. character
  count.
- Stagger the send across 30 minutes (batch of ~10% per 3 minutes) so
  the login endpoint doesn't see an instantaneous thundering herd when
  users tap through.
- Confirm opt-out segment is honoured — users who disabled push in
  settings must not be targeted.
