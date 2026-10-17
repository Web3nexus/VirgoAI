# DEC-0016: Skill Library

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Context**: The paste box picks an output (full video, image, audio). Users also need to pick how the AI should
  work: a product ad, a vertical short, a storyboard. The reference is the Buzzy skills library, a searchable set of
  cards with a category filter.
- **Decision**: A skill is data in `apps/api/app/Skills/SkillLibrary.php`: a key, label, category, the mode it runs in,
  a description for the card, defaults (format, quality), and the instructions the model is given. The library is
  served at `GET /api/v1/skills` without the instructions. A selected skill is applied as follows:
  - text runs: appended to the system prompt, after any format the tool already sets, so a JSON contract still wins;
  - image and video runs: placed as a brief in front of the request;
  - full video: added to the director's system prompt for the script;
  - audio runs: no effect (speech is not steered); the Audio Generator skill writes a script through the story tool instead.
  The chosen skill is stored with the run (`options.skill_key`) and with the production (`productions.skill_key`).
- **Consequences**: Skill instructions reach the provider, so they are part of the prompt cost, and the estimate for a
  full video includes them. Skills are reviewed in code, not edited by users, until a store is needed.
- **Rationale**: Keeping skills as code makes them testable and reviewable. A database table can come later if users
  need their own skills.
