Below is the master development prompt for your AI Content Studio, designed around React, Laravel, and PostgreSQL. It includes the automated production pipeline, Infoxchange-style creative tools, intelligent AI model routing, Seedance, Google Veo, GPT Image, approval checkpoints, and full-handoff automation.
I’m interpreting “postgrest” as PostgreSQL. If you mean PostgREST as the API layer, the architecture will need a small adjustment because Laravel is already providing the backend API.
The prompt is designed for your coding agent to inspect your existing project first, plan the implementation, and then build a working application rather than a static prototype.
MASTER DEVELOPMENT PROMPT
AI Content Studio — Multi-Model Creative AI and Automated Production Platform
1. YOUR ROLE
Act as a principal software architect, senior full-stack developer, AI integration engineer, workflow automation engineer, and UI/UX designer.
Your responsibility is to design, develop, test, and deliver a production-ready, browser-based AI Content Studio.
This is a complete software product, not a landing page, chatbot, mock dashboard, or collection of disconnected AI utilities.
You must inspect the existing codebase before making changes, preserve existing working functionality, and implement the platform incrementally.
Do not stop after producing a development plan. After inspecting the repository and identifying dependencies, proceed with implementation unless a genuinely blocking decision or missing credential prevents progress.
Do not claim a feature is complete unless its actual workflow has been implemented and tested.

2. PRODUCT VISION
Build an intelligent AI production workspace that transforms a YouTube URL, transcript, uploaded video, existing script, or original idea into a complete content package.
The application should combine the capabilities of a creative AI studio, video production workflow, and intelligent automation agent.
The user should be able to submit one input and let the system automatically perform the selected production tasks.
The system must orchestrate multiple AI models according to their capabilities, quality, cost, speed, and availability.
The user must not need to manually copy outputs between different modules.
Every module must be connected to a central production orchestrator.
The platform must support three automation modes:
Guided Mode.
Assisted Mode.
Full Handoff Mode.
The application must also provide an administrative interface for managing AI providers, models, costs, capabilities, and workflow execution.
The architecture must allow new AI providers and models to be added without rewriting the entire application.

3. REQUIRED TECHNOLOGY STACK
Frontend
Use:
React.
TypeScript.
Vite if the existing project does not use another established React build system.
Tailwind CSS.
A consistent component library, preferably shadcn/ui when compatible.
React Router or the routing system already used by the project.
TanStack Query for server-state management where appropriate.
React Hook Form and Zod for forms and validation.
Server-Sent Events or WebSockets for live workflow updates where appropriate.
Use reusable components, typed API contracts, modular feature folders, and maintainable state management.
Do not put the entire frontend into one component.
Backend
Use:
Laravel.
PHP version compatible with the selected Laravel release.
Laravel API routes.
Laravel queues and background jobs.
Laravel events and listeners.
Laravel notifications.
Laravel policies and authorization.
Laravel scheduler for periodic maintenance and monitoring tasks.
Redis for queues and caching when available.
Follow the existing Laravel version and project conventions unless there is a compelling technical reason to change them.
Database
Use PostgreSQL.
Design normalized database tables, appropriate indexes, foreign keys, transactions, and JSONB columns where flexible provider metadata or structured workflow configuration is appropriate.
Use Laravel migrations as the authoritative mechanism for database schema changes.
Do not introduce MySQL-specific syntax.
Use PostgreSQL-compatible queries and test migrations against PostgreSQL.
Media processing
Use FFmpeg for:
Video cutting.
Video concatenation.
Audio extraction.
Subtitle integration.
Resizing.
Aspect-ratio conversion.
Audio mixing.
Basic transitions.
Final video rendering.
Thumbnail compositing where appropriate.
Use background workers for expensive processing tasks.
Storage
Use an S3-compatible storage interface for generated images, videos, audio, transcripts, and other assets.
Support local storage during development.
Avoid storing large binary video and image files directly in PostgreSQL.
AI integrations
Use a provider-independent integration layer for:
Language models.
Image generation.
Image editing.
Video generation.
Video editing.
Speech-to-text.
Text-to-speech.
Voice dubbing.
Music and sound effects where available.
Other creative AI services.
Do not hardcode the entire application around one AI provider.

4. FIRST TASK: INSPECT THE EXISTING PROJECT
Before implementing anything, inspect the repository.
Determine:
Existing React structure.
Existing Laravel structure.
Authentication implementation.
Existing database schema.
Current Laravel and PHP versions.
Current React and Node versions.
Existing UI components.
API conventions.
Existing storage configuration.
Existing queue infrastructure.
Existing AI integrations.
Existing environment configuration.
Current testing setup.
Deployment architecture.
Identify reusable components and existing functionality.
Produce a concise implementation plan organized into working increments.
Then start implementation.
Do not delete or replace functioning code merely to simplify the project.
Do not create duplicate authentication systems, competing API clients, or redundant database tables when equivalent components already exist.
Never overwrite environment files or expose existing secrets.
If the repository contains a working product, extend it rather than rebuilding it from scratch.

5. APPLICATION STRUCTURE
Organize the product into the following primary areas.
Main navigation
Dashboard.
New Project.
Story Idea Generator.
Story Lab.
Content Mastermind.
Thumbnail Maker.
Video Clipper.
UGC Content Studio.
Character Forge.
Outfit Extractor.
Voice Image Editor.
YouTube Promotion.
My Projects.
Asset Library.
Workflow Activity.
Settings.
Administrative navigation
Overview.
AI Providers.
Model Registry.
Model Router.
Workflow Templates.
Job Queue.
Usage and Costs.
Provider Health.
Failed Jobs.
Users and Permissions.
System Configuration.
Audit Logs.
Implement role-based access control for administrative functionality.
Regular users must never be able to modify global provider credentials, system-wide routing rules, or another user’s projects.

6. DESIGN REQUIREMENTS
Build a professional creative SaaS interface.
The interface must feel like a real production workspace rather than a generic AI chatbot.
Use:
Clear visual hierarchy.
Consistent typography.
A restrained color palette.
Spacious layouts.
Reusable cards and panels.
Well-designed empty states.
Clear progress indicators.
Responsive layouts.
Accessible controls.
Consistent modal and drawer behavior.
Useful error messages.
Loading states and skeletons.
The dashboard must make the main action immediately obvious:
“Start a New Production”
The primary input component must support:
Pasting a YouTube URL.
Pasting a transcript.
Entering an original idea.
Uploading a transcript file.
Uploading a supported video file.
Selecting an existing project.
Allow drag-and-drop uploads.
The interface must clearly distinguish between:
An AI-generated prompt.
An actual generated image.
An actual generated video.
A pending generation job.
A completed downloadable asset.
Never display a successful-generation state before the backend has confirmed that the output exists.

7. CENTRAL CONTENT PRODUCTION ORCHESTRATOR
This is the core of the entire application.
Implement a durable workflow orchestration system in Laravel.
The orchestrator must manage dependencies, task execution, approvals, retries, failures, costs, and final packaging.
Do not implement the entire workflow as one enormous AI prompt or one long-running HTTP request.
Each production stage must be represented by a separate background job or workflow task.
Workflow capabilities
The orchestrator must support:
Sequential task execution.
Parallel task execution.
Conditional branches.
Dependency tracking.
Approval checkpoints.
User revisions.
Task cancellation.
Job retries.
Timeout handling.
Provider fallback.
Cost controls.
Workflow resumption.
Partial regeneration.
Progress tracking.
Final package generation.
Workflow states
Use explicit, validated states.
At minimum:
Draft.
Queued.
Running.
Awaiting Approval.
Awaiting Revision.
Paused.
Completed.
Failed.
Cancelled.
Implement legal state transitions in a dedicated domain service rather than allowing arbitrary status updates from controllers.
Persist workflow state in PostgreSQL.
A worker restart must not destroy the state of a project.
A browser refresh must not interrupt the workflow.
The user must be able to leave the application and return later.
Idempotency
Implement duplicate-job protection.
A repeated request must not unintentionally trigger the same expensive AI generation multiple times.
Use stable task identifiers, unique constraints where appropriate, idempotency keys, and safe retry handling.
Distinguish between retrying a failed HTTP request and submitting a new paid generation job.
Never blindly resubmit a paid operation when its previous result is uncertain.

8. AUTOMATION MODES
Every project must have an execution mode.
Users must also be able to define a default mode in Settings.
MODE A: GUIDED
Pause at configured checkpoints.
Display the generated output and the next planned action.
Provide these controls:
Approve and Continue.
Edit Before Continuing.
Regenerate.
Skip Stage.
Pause Project.
Cancel Project.
The next stage must not execute until the configured approval requirement is satisfied.
MODE B: ASSISTED
Automatically perform routine operations.
Pause when:
A configured approval checkpoint is reached.
A significant decision is required.
A requested output cannot be generated.
A provider fails without a safe automatic fallback.
A cost limit would be exceeded.
An important requirement cannot be preserved.
An external action requires confirmation.
MODE C: FULL HANDOFF
Execute all eligible workflow stages automatically.
The user should be able to:
Paste a YouTube URL or transcript.
Select a saved production profile.
Start the workflow.
Leave the application.
Return to find the completed project and assets.
Full Handoff must automatically transfer outputs between modules.
It must not require the user to manually copy scripts, prompts, character descriptions, or scene data.
Full Handoff must still respect:
Configured budgets.
Provider permissions.
User authorization.
Source-access restrictions.
Mandatory approvals.
Safety controls.
Paid advertising confirmation.
Irreversible external actions.
The application must clearly distinguish automatic internal production from actions that require an external confirmation.

9. APPROVAL AND REVISION ENGINE
Implement a reusable approval engine.
Each workflow stage must be able to declare whether approval is:
Always required.
Required only in Guided Mode.
Required when configured by the user.
Not required for that stage.
The exact rules must be centralized and consistently applied.
Approval interface
When a stage finishes, show:
Stage name.
Current status.
Output preview.
Input dependencies.
Next stage.
Estimated additional cost when available.
Relevant warnings.
Available actions.
Edit Before Continuing
When a user chooses to edit:
Open the relevant editor.
Load the current version.
Preserve the previous version.
Allow modifications.
Validate the new version.
Save a new revision.
Recalculate affected dependencies.
Continue according to the approval policy.
Regeneration
Allow regeneration of:
The entire stage.
A selected scene.
A single character.
A specific image.
A narration segment.
A particular video clip.
A thumbnail variation.
Do not regenerate unaffected assets unnecessarily.
If an upstream asset changes, mark dependent assets as potentially outdated.
The user must be able to see which outputs need regeneration.

10. AI PROVIDER ADAPTER ARCHITECTURE
Implement a provider-independent integration layer.
Create separate adapters for each provider and capability.
The architecture must normalize:
Model selection.
Input validation.
Request construction.
Authentication.
Task submission.
Asynchronous processing.
Status updates.
Result retrieval.
Error handling.
Usage information.
Cost metadata.
Do not force every provider into an identical API request format internally.
Use a shared interface with provider-specific implementations.
A provider must be independently enableable, disableable, testable, and replaceable.
Required provider categories
Language models
Prepare integrations for suitable models from:
OpenAI.
Google Gemini.
Anthropic.
Other compatible providers.
Use language models for:
Story generation.
Script development.
Transcript analysis.
Creative planning.
Prompt generation.
Structured output generation.
Workflow decisions where appropriate.
Image models
Prepare integrations for:
OpenAI GPT Image.
Google image-generation and editing models.
FLUX models.
Ideogram.
Other compatible providers.
Use image models for:
Character creation.
Scene generation.
Thumbnail artwork.
Image editing.
Visual concepts.
Story illustrations.
Video models
Prepare integrations for:
ByteDance Seedance.
Google Veo.
Runway video models.
Kling.
Other compatible video-generation services.
Use video models for:
Text-to-video.
Image-to-video.
Reference-guided video where supported.
Cinematic shots.
Scene animation.
Other documented video operations.
Voice and audio
Prepare integrations for suitable providers supporting:
Speech-to-text.
Text-to-speech.
Narration.
Dubbing.
Voiceover generation.
Sound effects.
Music generation where available.
Important implementation rule
These are integration targets, not assumed working integrations.
Before implementing each adapter, verify the current official API documentation, model identifier, required credentials, supported regions, capabilities, and pricing.
Do not assume a model available in a consumer application is available through a public API.
Do not invent endpoints or model identifiers.
If credentials are missing, implement the integration structure and report the missing configuration accurately.
Never simulate a real provider response and present it as genuine AI output.

11. INTELLIGENT AI MODEL ROUTER
Implement a central Model Router.
Every creative operation should request a compatible model through this service unless the user explicitly selects a compatible model.
The router must consider:
Task type.
Supported input formats.
Supported output formats.
Required reference assets.
Image or video resolution.
Video duration.
Required audio capabilities.
Quality requirements.
Estimated cost.
Expected processing time.
Availability.
Historical success rate.
User preferences.
Project budget.
Routing strategies
Support three strategies.
QUALITY FIRST:
 Prefer the highest-performing compatible model within the permitted budget.
COST OPTIMIZED:
 Prefer an economical model that satisfies minimum quality requirements.
BALANCED:
 Balance quality, cost, and processing time.
The router must not claim that one model is universally superior.
Use capability metadata, verified documentation, and evaluation results.
Model registry
Create a database-backed model registry containing:
Provider.
Model identifier.
Display name.
Supported tasks.
Supported inputs.
Supported outputs.
Reference-image support.
Reference-video support.
Audio support.
Supported resolutions.
Duration limits.
Pricing metadata.
Availability.
Last verification date.
Administrative enablement status.
Allow administrators to configure:
Preferred models.
Fallback models.
Routing strategies.
Per-task restrictions.
Maximum costs.
Provider priorities.
Fallback
When a provider fails:
Determine whether the error is recoverable.
Retry only when safe.
Respect rate limits.
Check the task’s idempotency state.
Identify compatible alternatives.
Check the budget.
Select a fallback according to the configured policy.
Record the reason for the switch.
Do not silently change important output requirements.
If no compatible fallback exists, pause or fail the task with a clear explanation.

12. STORY IDEA GENERATOR
Support:
YouTube URL.
Transcript.
Uploaded transcript.
Existing story.
Original idea.
Authorized reference material.
Processing:
Obtain accessible source content.
Generate a source summary.
Identify central themes.
Identify narrative conflicts.
Generate multiple story concepts.
Adapt the selected concept.
Generate a title.
Generate a logline.
Generate a synopsis.
Generate an outline.
Generate a full script when requested.
Cultural settings
Support:
Nigeria.
Other user-selected African settings.
United States.
International.
Custom settings.
For Nigerian stories, allow specific settings such as Lagos, Abuja, and other selected locations.
Do not treat Africa as one uniform culture.
Support language, genre, tone, audience, and duration preferences.
Distinguish fictional adaptations from factual summaries.
Do not reproduce another creator’s protected script or closely imitate a living creator’s distinctive style.
Save outputs as structured records.

13. STORY LAB
Convert a story into a production-ready visual plan.
Generate:
Character descriptions.
Character reference prompts.
Environment descriptions.
Scene breakdowns.
Cinematic image prompts.
Camera instructions.
Composition.
Lighting.
Color direction.
Continuity requirements.
Aspect ratios.
Negative prompts when supported.
Assign persistent IDs to:
Projects.
Characters.
Locations.
Scenes.
Assets.
Each scene must reference its relevant characters, locations, and script passages.
Generate actual images through the selected image provider when requested.
Track prompt generation and image generation as separate operations.

14. CONTENT MASTERMIND
Generate a complete short-form production kit.
Default duration: 60 seconds.
Allow configurable durations.
Generate:
Concept.
Hook.
Script.
Scene breakdown.
Dialogue.
Narration.
Image prompts.
Images where requested.
Video-generation instructions.
Voiceover.
Subtitle file.
Sound-effect cues.
Music suggestions.
Title.
Description.
Thumbnail concept.
Call to action.
Estimate narration duration based on the actual script.
Allow component-level regeneration.

15. THUMBNAIL MAKER
Support YouTube, TikTok, Facebook, and configurable output formats.
Generate concepts based on the actual content.
Support:
Artwork generation.
Headline suggestions.
Text overlays.
Typography.
Cropping.
Positioning.
Brand colors.
Alternative variations.
Export presets.
Use deterministic text rendering where appropriate.
Do not depend on an image model to spell every headline correctly.
Save original artwork and final composed thumbnails separately.

16. AI VIDEO CLIPPER
Support authorized uploads and permitted video-processing workflows.
Pipeline:
Extract metadata.
Extract audio.
Transcribe speech.
Analyze transcript segments.
Identify candidate highlights.
Generate timestamped clip suggestions.
Render previews.
Add subtitles.
Reframe for vertical platforms.
Allow user adjustments.
Render final clips.
Generate titles and descriptions.
Generate thumbnail concepts.
Package approved results.
Use FFmpeg for media processing.
Use background workers for large video operations.
Preserve source timestamps.
Do not assume a transcript alone provides the visual information needed for precise video editing.
When the workflow requires visual analysis, use an appropriate video-analysis capability or frame-extraction process.
The system must not claim to have generated finished clips when it has produced only timestamps or instructions.

17. UGC CONTENT STUDIO
Generate a six-clip advertising package.
Each package must contain:
Campaign concept.
Target audience.
Six distinct video concepts.
Six scripts.
Six hooks.
Scene instructions.
Voiceover suggestions.
Calls to action.
Captions.
Thumbnail or cover concepts.
Where actual media generation is requested and available, generate the assets.
Maintain consistency across the campaign.
Track scripts, prompts, and generated media independently.

18. CHARACTER FORGE
Support:
Character concepts.
Detailed descriptions.
Clothing and accessories.
Front-view references.
Side-view references.
Back-view references.
Three-quarter views.
Expression references where supported.
Multiple outfits.
Character continuity notes.
Use reference images where supported.
Store canonical character descriptions.
Allow Story Lab to reuse character records automatically.
Do not guarantee exact consistency across independently generated images.
Allow users to regenerate one character view without recreating the entire project.

19. OUTFIT EXTRACTOR
Allow users to upload authorized reference images.
Analyze visible clothing and generate structured descriptions of:
Garments.
Colors.
Patterns.
Layering.
Accessories.
Footwear.
Silhouette.
Styling.
Describe uncertain fabric composition cautiously.
Generate reusable outfit prompts.
Allow outfit descriptions to be transferred to Character Forge and Story Lab.

20. VOICE IMAGE EDITOR
Support voice-driven image editing.
Workflow:
Upload an image.
Record or upload a voice instruction.
Transcribe the instruction.
Display the interpreted instruction.
Allow editing.
Submit the request to an image-editing provider.
Display the result.
Allow revisions.
Save the approved asset.
Also support typed instructions.
Preserve the original image.
Store revisions separately.

21. YOUTUBE PROMOTION
Generate campaign strategies for eligible YouTube videos.
Support:
Campaign objective.
Audience recommendations.
Advertising copy.
Creative recommendations.
Budget scenarios.
Campaign dates.
Tracking recommendations.
Campaign setup instructions.
Support a configurable minimum video duration, including eight minutes when selected.
Do not launch paid advertising automatically without explicit authorization.
If Google Ads integration is implemented, use official authentication and API mechanisms.
Before launching a campaign, require explicit confirmation of the campaign details and spending commitment.
Never guarantee advertising results.

22. AUTOMATIC PRODUCTION PIPELINES
Create reusable workflow templates.
At minimum:
Pipeline A: Transcript to Story
Input:
 YouTube URL or transcript.
Output:
Source analysis.
Story ideas.
Selected concept.
Script.
Characters.
Scenes.
Image prompts.
Thumbnail concepts.
Pipeline B: Story to Cinematic Video
Input:
 Approved story or script.
Output:
Characters.
Scene breakdown.
Reference images.
Generated images.
Generated video shots where supported.
Narration.
Subtitles.
Edited video.
Thumbnail.
Description.
Pipeline C: Long Video to Social Clips
Input:
 Authorized video file or supported source.
Output:
Transcript.
Clip candidates.
Timestamped highlights.
Vertical clips.
Captions.
Titles.
Thumbnail concepts.
Pipeline D: Complete Short-Form Production
Input:
 Original idea or transcript.
Output:
Hook.
Script.
Scene plan.
Images or generated video shots.
Narration.
Subtitles.
Finished short video.
Thumbnail.
Social captions.
Pipeline E: Six-Clip UGC Campaign
Input:
 Product, service, or campaign brief.
Output:
Six concepts.
Six scripts.
Six production plans.
Requested visual assets.
Captions.
Calls to action.
The orchestrator must transfer structured data between stages automatically.
Users must never need to copy and paste between modules.

23. PROJECT AND ASSET MANAGEMENT
Each project must store:
Original input.
Source metadata.
Transcript.
Analysis.
Story concepts.
Script revisions.
Character records.
Scene records.
Image prompts.
Generated images.
Narration.
Subtitles.
Generated videos.
Clips.
Thumbnails.
Approval history.
Workflow history.
Provider usage.
Costs when available.
Errors and retries.
Implement:
Search.
Filtering.
Asset previews.
Download.
Version history.
Project duplication.
Archive.
Dependency tracking.
Use a dependency graph to identify which assets are affected when an upstream item changes.

24. POSTGRESQL DATA MODEL
Design normalized PostgreSQL tables using Laravel migrations.
Create suitable tables for:
users.
projects.
project_sources.
source_transcripts.
story_concepts.
scripts.
script_versions.
characters.
scenes.
assets.
asset_versions.
ai_providers.
ai_models.
provider_capabilities.
workflow_templates.
workflows.
workflow_tasks.
task_dependencies.
approval_requests.
approval_decisions.
ai_usage_records.
project_budgets.
notifications.
audit_logs.
Adjust this list to reuse existing tables where appropriate.
Use JSONB for genuinely variable structured data, not as a substitute for relational modeling.
Use foreign keys, indexes, unique constraints, and transactions.
Use PostgreSQL-compatible syntax.
Avoid storing generated media files as database blobs.
Use secure ownership and authorization checks for every project and asset request.

25. API DESIGN
Use a consistent, versioned Laravel API.
For example:
GET /api/v1/projects
POST /api/v1/projects
GET /api/v1/projects/{project}
POST /api/v1/projects/{project}/start
GET /api/v1/projects/{project}/workflow
POST /api/v1/workflows/{workflow}/pause
POST /api/v1/workflows/{workflow}/resume
POST /api/v1/workflows/{workflow}/cancel
POST /api/v1/approvals/{approval}/approve
POST /api/v1/approvals/{approval}/revise
POST /api/v1/approvals/{approval}/regenerate
GET /api/v1/assets/{asset}
POST /api/v1/assets/{asset}/regenerate
GET /api/v1/providers
GET /api/v1/models
POST /api/v1/admin/models/{model}/test
These routes are proposed contracts, not a requirement to duplicate existing routes.
Use Laravel Form Requests for validation.
Use Policies for authorization.
Use API Resources or equivalent response transformers.
Return consistent error structures.
Never trust project IDs supplied by the frontend without checking ownership and authorization.

26. REAL-TIME PROGRESS
The dashboard must show live workflow progress.
Display:
Current stage.
Overall status.
Completed stages.
Running stages.
Queued tasks.
Approval checkpoints.
Failed tasks.
Retry attempts.
Generated assets.
Estimated cost when available.
Actual usage when available.
Use Server-Sent Events, WebSockets, or reliable polling according to the existing architecture.
Do not keep long-running HTTP requests open while a model generates a video.
Background jobs must expose progress through persisted state and appropriate notifications.

27. SECURITY
Implement:
Secure authentication.
Role-based access control.
Server-side authorization.
Rate limiting.
Request validation.
File validation.
Upload limits.
Secure credential management.
Protection against unauthorized project access.
Safe handling of external URLs.
Provider usage limits.
Audit logs for sensitive administrative actions.
Signed download URLs where appropriate.
Appropriate temporary-file cleanup.
Never expose provider API keys to React.
Prevent unauthorized users from accessing another user’s transcripts, generated media, or workflow history.
Do not automatically download arbitrary URLs without validating them.
Use permitted source-access mechanisms for YouTube and other services.
Respect copyright, privacy, and applicable platform restrictions.

28. COST MANAGEMENT
Create a cost-control system.
Support:
Per-project budgets.
Per-user usage limits.
Configurable spending thresholds.
Provider-specific pricing metadata.
Estimated generation costs.
Actual cost records when available.
Usage reports.
Budget warnings.
Job cancellation.
Before expensive operations, verify the project’s remaining budget.
If the budget is insufficient, pause and request a decision.
Do not represent estimated prices as guaranteed charges.
Account for failed or partially completed operations when reliable billing information is available.

29. TESTING
Implement automated tests throughout development.
Backend tests must cover:
Authentication.
Project ownership.
API validation.
Workflow transitions.
Approval policies.
Revisions.
Task dependencies.
Job retries.
Idempotency.
Cancellation.
Budget enforcement.
Provider fallback.
Authorization.
Asset ownership.
Failure recovery.
Frontend tests must cover:
Project creation.
Input validation.
Execution-mode selection.
Approval controls.
Editing.
Workflow progress.
Error states.
Asset previews.
Project navigation.
Provider integrations must have mocked tests and separate real integration tests when credentials are available.
Do not run expensive production generations as part of every ordinary automated test.
Provide documented commands for running tests.

30. IMPLEMENTATION PHASES
Implement in working increments.
PHASE 1: FOUNDATION
Build:
Repository inspection.
React application structure.
Laravel API integration.
PostgreSQL migrations.
Authentication and authorization.
Project management.
Asset management.
Provider configuration.
Workflow engine.
Approval engine.
Job queue.
Progress tracking.
Acceptance:
 A user can create a project, start a test workflow, review a checkpoint, edit an output, approve it, and resume the workflow.
PHASE 2: AI ORCHESTRATION
Build:
Provider adapters.
Model registry.
Model router.
Capability validation.
Cost estimation.
Fallback policies.
Provider health monitoring.
Usage logging.
Acceptance:
 The application can submit a supported task to a configured provider, receive the result, save it, and route the output into the next stage.
PHASE 3: STORY AND IMAGE STUDIO
Build:
Story Idea Generator.
Transcript processing.
Story Lab.
Content Mastermind.
Character Forge.
Thumbnail Maker.
Outfit Extractor.
Voice Image Editor.
Acceptance:
 Users can complete the relevant workflows using actual configured providers.
PHASE 4: VIDEO PRODUCTION
Build:
Video uploads.
Transcription.
Video Clipper.
Scene generation.
Video-model integration.
Narration.
Subtitle generation.
FFmpeg rendering.
Final export.
Acceptance:
 The system can produce a real video from supported source material and configured generation assets.
PHASE 5: AUTOMATED CAMPAIGNS
Build:
UGC Content Studio.
YouTube promotion planning.
Optional advertising integration.
Batch generation.
Automated packaging.
Completion notifications.
Acceptance:
 The system can execute configured multi-stage campaigns and save their outputs.
PHASE 6: HARDENING
Complete:
Integration testing.
Security review.
Failure recovery.
Cost controls.
Performance testing.
Queue monitoring.
Documentation.
Deployment configuration.
Do not mark the platform production-ready until critical workflows have passed end-to-end testing.

31. IMPLEMENTATION RULES
Follow these rules throughout development.
Inspect before changing.
Reuse existing working components.
Implement real functionality.
Avoid fake data in production workflows.
Never invent provider capabilities.
Never expose API credentials.
Use PostgreSQL-compatible migrations and queries.
Keep business logic out of React components.
Keep controllers thin.
Use dedicated services for orchestration and routing.
Use background jobs for expensive tasks.
Persist workflow state.
Make paid operations safe against duplicate submissions.
Provide meaningful error messages.
Add tests for critical behavior.
Preserve previous asset versions.
Respect configured approval requirements.
Respect spending limits.
Document missing credentials and external dependencies.
Continue implementation after planning unless blocked by a genuine dependency or decision.
Do not claim to have tested a real integration when only a mocked test has passed.
Do not claim a video is generated when only a video prompt exists.
Do not claim a provider is integrated until its actual API workflow has been implemented and verified.

32. FINAL ACCEPTANCE CRITERIA
The finished application must allow an authorized user to:
Open the React web application.
Create a project.
Paste a supported YouTube URL or transcript.
Select a production profile.
Choose Guided, Assisted, or Full Handoff mode.
Start the workflow.
Have Laravel coordinate the selected production stages.
Have the Model Router select compatible configured AI models.
Pass structured outputs between stages automatically.
Review, edit, approve, or regenerate outputs where required.
Resume interrupted workflows.
Track actual workflow progress.
Control project spending.
Download generated assets.
View the complete project history.
Full Handoff must execute all eligible configured stages without routine manual intervention.
The application must use PostgreSQL as its database, React as its frontend, and Laravel as its backend.
The result must be a functioning AI production platform with a durable workflow engine, a modular AI provider architecture, intelligent model routing, and end-to-end automation.
Begin by inspecting the repository, reporting the existing architecture and blockers, and then implementing Phase 1.
Three important decisions before implementation
1. React + Laravel + PostgreSQL is a suitable stack. Laravel will handle authentication, project management, orchestration, and API integrations. React will handle the interactive production workspace. PostgreSQL will store projects, workflow states, assets metadata, and model configuration.
2. Build the orchestration engine first. This is what allows a single input to trigger multiple AI models and automatically hand results from one stage to the next.
3. Integrate models through verified APIs. Seedance, Veo, GPT Image, and other creative models may have different access requirements, pricing, and capabilities. The application should support them through replaceable adapters rather than assuming every model is available through one service.
One final technical note: If you actually intend to use PostgREST as a separate REST API server in addition to PostgreSQL, tell your coding agent explicitly. The prompt above assumes Laravel is the main backend API and PostgreSQL is the database, which avoids maintaining two competing API layers for the same data.

