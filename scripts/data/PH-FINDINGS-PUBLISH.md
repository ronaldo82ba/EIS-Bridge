# PH Findings · 60-Day Series (public, daily)

The series hub is public at `/insights/ph-findings/`.

**Rule:** only generate/commit day pages through the current publish date.
Today (series start): Day 01 only. Do not ship `day-02.html` … `day-60.html` until each day’s date.

```bash
# Publish through a given day (Asia/Manila dates in the pack)
node scripts/generate-ph-findings-insights.mjs --through=YYYY-MM-DD
# then CodeDEV marketing deploy of release/rc1
```

Source pack: `scripts/data/ph-findings-60.json`  
Generator: `scripts/generate-ph-findings-insights.mjs`
