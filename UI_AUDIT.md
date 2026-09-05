# QUIZLY UI/UX REDESIGN AUDIT & SYSTEM MAP

## 1. System Inventory

### Core Public & Auth Pages
1. **Landing Page** (`index.php`)
   - *Current State:* Dark background (`#070A12`), dark glass hero, dark cards, glowing text.
   - *New Design:* Pure white background (`#FFFFFF`), light neutral canvas (`#F8FAFF`), vibrant purple/blue gradient accent headlines (`#6D28D9` -> `#2563EB`), floating white cards with soft multi-layer shadow, clean modern hero mockups.
   - *Responsive Fix:* Mobile menu drawer, mobile-optimized join box, stackable feature cards.

2. **Join Quiz Page** (`join.php`)
   - *Current State:* Dark glass card for room code entry.
   - *New Design:* Clean white card (`#FFFFFF`), 24px rounded corners, crisp `#E5E7EB` border, centered 6-digit pin input with high contrast focus ring, purple-to-blue gradient CTA button (`#6D28D9` -> `#2563EB`).
   - *Responsive Fix:* Full width card (`calc(100% - 32px)`), touch-optimized inputs.

3. **Teacher Login** (`login.php`)
   - *Current State:* Translucent dark login container.
   - *New Design:* Modern split layout (Desktop: Left brand hero illustration area with soft pastels, Right white login card). Mobile: Single column clean card, rounded input fields with clear focus indicators and password toggle.

4. **Registration** (`register.php`)
   - *Current State:* Dark form with cramped grid on mobile.
   - *New Design:* Clean single/multi-step layout, white card container, step progress indicator, mobile-first single column inputs.

---

### Teacher Dashboard & Management
5. **Teacher Dashboard Overview** (`dashboard/index.php`)
   - *Current State:* Top dark navbar, dark stats grid, dark table.
   - *New Design:* Modern white layout with desktop sidebar (250px) and top bar. Multi-color stat cards with subtle pastel tint backgrounds (Purple, Blue, Pink, Cyan), modern table with rounded rows and status badges.
   - *Responsive Fix:* Mobile drawer sidebar, 1-card-per-row grid on mobile, responsive table wrapper with horizontal scroll protection.

6. **My Quizzes** (`dashboard/quizzes.php`)
   - *Current State:* Dark table listing quizzes.
   - *New Design:* Clean white card, action buttons with vibrant gradient icons, badge indicators for published/draft quizzes, modal popups for delete confirmation.

7. **Quiz Builder / Creation & Editing** (`dashboard/create_quiz.php`, `dashboard/edit_quiz.php`)
   - *Current State:* Multi-column form that squashes on mobile, dark question blocks.
   - *New Design:* Responsive 3-panel architecture (Question selector sidebar -> Question Editor center -> Settings panel right). Mobile converts selector into a horizontal scroll bar with `overflow-x: auto` and settings into a bottom sheet drawer.

8. **Quiz Preview & Launch** (`dashboard/view_quiz.php`)
   - *Current State:* Dark quiz details card with start button.
   - *New Design:* Clean white overview card, question preview accordion, prominent gradient "Launch Live Session" button.

9. **Session Results & Analytics** (`dashboard/results.php`, `dashboard/analytics.php`)
   - *Current State:* Dark charts and raw tables.
   - *New Design:* White metric cards, lightweight CSS/JS responsive charts, question accuracy breakdowns with green/amber/red indicator bars.

---

### Live Session Experience
10. **Student Live Screen & Lobby** (`live/student.php`)
    - *Current State:* Dark mobile UI with dark option buttons.
    - *New Design:* Clean white student interface. Vibrant, distinct touch-friendly answer buttons (Option A: Coral/Red `#EF4444`, B: Sky/Blue `#2563EB`, C: Amber/Yellow `#F59E0B`, D: Emerald/Green `#10B981`) on white/pastel cards. Touch targets minimum 52px high. Animated waiting lobby with participant count and custom avatar icons.

11. **Host Control Screen** (`live/host.php`)
    - *Current State:* Dark grid host room with answer bars.
    - *New Design:* Clean presentation control room on white/light backdrop (`#F8FAFF`). Live participant chips with smooth entrance animations, real-time bar distributions, large control buttons (`START`, `END`, `LEADERBOARD`, `NEXT`).

12. **Presentation Big-Screen** (`live/presentation.php`)
    - *Current State:* Dark auditorium mode.
    - *New Design:* High contrast, crisp presentation mode on light backdrop, huge typography readable from a distance, animated top-3 podium leaderboard.

---

### Admin Portal
13. **Platform Admin Portal** (`admin/index.php`)
    - *Current State:* Dark admin view.
    - *New Design:* Shared White + Multi-Color design system, matching sidebar, user/org management tables, clean system metric cards.

---

## 2. Design Tokens Strategy

```css
:root {
    /* Canvas & Backgrounds */
    --bg-body: #FFFFFF;
    --bg-surface: #FFFFFF;
    --bg-subtle: #F8FAFF;
    --bg-muted: #F1F5F9;
    
    /* Typography Colors */
    --text-primary: #111827;
    --text-secondary: #4B5563;
    --text-muted: #64748B;
    --text-light: #94A3B8;

    /* Brand Accents */
    --brand-purple: #6D28D9;
    --brand-purple-light: #F3E8FF;
    --brand-blue: #2563EB;
    --brand-blue-light: #EFF6FF;
    --brand-cyan: #06B6D4;
    --brand-cyan-light: #ECFEFF;
    --brand-pink: #EC4899;
    --brand-pink-light: #FDF2F8;
    --brand-magenta: #D946EF;

    /* Status Colors */
    --success: #10B981;
    --success-light: #ECFDF5;
    --warning: #F59E0B;
    --warning-light: #FFFBEB;
    --danger: #EF4444;
    --danger-light: #FEF2F2;

    /* Gradients */
    --grad-primary: linear-gradient(135deg, #6D28D9 0%, #2563EB 100%);
    --grad-secondary: linear-gradient(135deg, #2563EB 0%, #06B6D4 100%);
    --grad-accent: linear-gradient(135deg, #6D28D9 0%, #EC4899 100%);

    /* Layout & Borders */
    --border-light: #E5E7EB;
    --border-hover: #CBD5E1;
    --radius-sm: 8px;
    --radius-md: 14px;
    --radius-lg: 20px;
    --radius-full: 9999px;

    /* Elevation & Shadows */
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
    --shadow-lg: 0 10px 25px -5px rgba(109, 40, 217, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
}
```
