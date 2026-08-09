# PH Findings · 60-Day Series (embargoed)

Public Insights pages for this series are **not** live during the 60-day social posting window
(2026-08-09 through 2026-10-07).

- Source pack: `scripts/data/ph-findings-60.json`
- Generator: `scripts/generate-ph-findings-insights.mjs`
- Public HTML target (after series completes): `insights/ph-findings/`

Do **not** commit generated HTML under `insights/ph-findings/` until the series has finished
and Ronaldo approves posting the full pack to eisbridge.com Insights.

After 2026-10-07 (or when approved):

```bash
node scripts/generate-ph-findings-insights.mjs --through=2026-10-07
# then CodeDEV marketing deploy of release/rc1
```
