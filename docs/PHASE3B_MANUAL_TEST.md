# Manual Verification Queue Test Guide

Use only the competition instance and synthetic students (`9999` prefix) or consenting adult volunteers. Do not test with real student data.

## Preparation

1. Enable `FEATURE_TRUST_ENGINE_ENFORCE` only on the competition instance and run the competition migrations.
2. If the consent gate is enabled, use only a reviewed and approved consent version. A withdrawn or outdated consent prevents a pending attempt from being approved.
3. Ensure the student has no attendance row for an arrival test. For a departure test, record an arrival first.
4. Sign in as an administrator using the application's existing admin guard. No new role system is introduced.

## Review flow

1. Submit a face attendance attempt that receives a `review` verdict (score 40–70 under the default thresholds, or the bounded liveness-unavailable fallback).
2. Confirm the student receives the message that admin verification is required.
3. Before any admin decision, confirm no row was written to `presensi`. The item should appear in the admin review queue with the score, reason, attempted time, and an admin-only private snapshot link.
4. Open the snapshot as an admin. Confirm that a student session cannot access the review page, decision endpoint, or snapshot.
5. Test rejection: reject an item and confirm no row was written to `presensi`.
6. Test approval on a separate pending item: approve an arrival and confirm exactly one arrival row is written, with the original attempt time and the captured photo. Repeat with a departure after an arrival and confirm only the departure fields are updated.
7. Attempt to approve a second time, approve after the attendance state has changed, and approve after consent is withdrawn. Each must be refused without a second attendance write.
8. Disable `FEATURE_TRUST_ENGINE_ENFORCE` and confirm the original attendance route behavior and route registration return to their flag-off state.

The challenge evidence is derived in the browser, and the face distance is still client-supplied. This guide does not establish resistance to spoofing or a measured FAR/FRR result.
