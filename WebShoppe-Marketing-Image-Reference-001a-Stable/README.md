# WebShoppe marketing → EIS Bridge image reference pack

**Doc ID:** `WebShoppe-Marketing-Image-Reference-001a-Stable`  
**Saved:** 2026-08-07  
**Location:** `C:\laragon\www\EIS Bridge\WebShoppe-Marketing-Image-Reference-001a-Stable`  
**Purpose:** Snapshot of how [webshoppeph.com](https://webshoppeph.com/) looks today, collect its images, and define how to generate **similar-looking** images that show **EIS Bridge activity** for eisbridge.com marketing.

---

## 1) How webshoppeph.com is doing (live check)

| Check | Result |
|-------|--------|
| URL | https://webshoppeph.com/ |
| Stack | Vite React SPA (Path B marketing site) — `data-theme`, `/assets/index-*.js` |
| Theme | Light brand default + **live Dark toggle** in header |
| Nav | Home · Featured Offers · Contact · Solutions · Book free consultation |
| Tone | Professional enablement platform; navy/white; gold tagline rule |
| EIS Bridge mention | Featured offer card + CTAs (“Ask about EIS Bridge™”) |
| Visual strength | Strong — real hero photo + offer photos + systems photo |
| Gaps vs eisbridge.com | webshoppeph.com has **photos**; eisbridge.com is still **text-heavy** |

**Verdict:** The WebShoppe marketing site looks complete and trustworthy. EIS Bridge’s own site should borrow this **photo/illustration polish** while keeping EIS Bridge product truth (connector / middleware, not POS replacement, no fake BIR accreditation).

---

## 2) Images collected from the live site

All assets currently used as CSS backgrounds on webshoppeph.com:

| Live URL | Local copy | Role on WebShoppe site |
|----------|------------|-------------------------|
| `/images/hero-team.png` | `live-site-images/hero-team.png` · `collected-images/hero-team.png` | Full-bleed hero — collaboration / client solutions team |
| `/images/offer-enablement.png` | `…/offer-enablement.png` | Featured offer — Business Enablement Package |
| `/images/offer-eis.png` | `…/offer-eis.png` | Featured offer — EIS Bridge™ card |
| `/images/systems.png` | `…/systems.png` | Training & operations section |
| `/favicon.svg` | `live-site-images/favicon.svg` | Site icon |

Also mirrored from local project `C:\laragon\www\webshoppe-marketing\public\images\` into `collected-images/`.

**Note:** Old Shopify CDN URLs for the previous storefront returned empty/challenge stubs on this pass — live marketing site no longer depends on those for the homepage.

---

## 3) What these images “feel” like (style DNA to copy)

Use this when prompting Canva / Gemini:

- Bright, high-key, modern office / workshop  
- Filipino / Southeast Asian professionals preferred  
- Business-casual, collaborative, laptop + large screen  
- Soft depth of field; left side often open for text overlay  
- Navy / blue UI accents on screens OK  
- Not neon, not cyberpunk, not purple AI slop  
- Photoreal **or** matching photoreal AI — consistent set  

---

## 4) Pivot idea — same look, EIS Bridge activity

Keep the **WebShoppe visual quality**, change the **story on screen** to EIS Bridge work:

| WebShoppe scene | EIS Bridge equivalent activity |
|-----------------|--------------------------------|
| Team pointing at generic analytics dashboard | Team reviewing **POS → Bridge → EIS queue** status on a wall screen |
| Enablement package lifestyle | Vendor onboarding: API keys, sandbox, Standard Sale Object mapping |
| EIS Bridge card (generic) | Closer shot: merchant POS still selling while bridge shows **queued** transmission |
| Systems / ops training | Certification playbook workshop: CERT/PTT checklist (no fake BIR seals) |
| Hero collaboration | Partner workshop: POS vendor + WebShoppe integrating Vendor API |

### Product-truth locks (must appear in every prompt)

- Connector / middleware — **not** a POS or ERP  
- **No** BIR logos, seals, or “BIR Accredited” badges  
- Safe: queued transmission, Vendor API, any POS, audit-ready exports  
- Merchant still owns CERT / PTT  

---

## 5) Folder contents

```
WebShoppe-Marketing-Image-Reference-001a-Stable/
├── README.md                          ← this file
├── 01-SITE-STATUS.md
├── 02-IMAGE-INVENTORY.md
├── 03-EIS-BRIDGE-IMAGE-BRIEF.md
├── 04-CANVA-DREAM-LAB-PROMPTS.md
├── 05-GEMINI-PROMPTS.md
├── collected-images/                  ← copies from webshoppe-marketing
├── live-site-images/                  ← downloaded from webshoppeph.com
└── image-urls.txt
```

Related packs in CodeRnD (prompts for eisbridge.com gaps):

- `EIS-Bridge-Canva-AI-Image-Prompt-Pack-001a-Stable.md`  
- `EIS-Bridge-Gemini-Image-Prompt-Pack-001a-Stable.md`  

---

## 6) Recommended next step

1. Open `04-CANVA-DREAM-LAB-PROMPTS.md`  
2. Generate 5–8 EIS Bridge activity photos matching WebShoppe style  
3. Place into eisbridge.com hero + stakeholder/feature sections  
4. Keep abstract isometric set as optional secondary style (from earlier Canva pack)  
