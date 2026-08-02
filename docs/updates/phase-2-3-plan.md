# Phase 2 & Phase 3 Combined Implementation Plan

We will implement all features of both **Phase 2** and **Phase 3** as requested by the user.

---

## 1. Background & Motivation

The Project Tracker system currently runs Phase 1 successfully with green tests. To deliver a complete product experience for both developers (Admins) and customers (Users), we need to:
- Establish interactive communication via polymorphic **Comments** on tasks.
- Keep users and admins informed with a robust queueable **Notification** system.
- Allow developers to invite clients easily via secure, expiring **Invitation Links**.
- Log all status changes for auditing via **Task Status Histories**.
- Support actual **Payments** (tracking records) instead of a manual paid_amount field, using Observers for synchronization.
- Generate aggregated **Monthly Work-Hour Reports** for the developer.
- Enhance the frontend task management with a drag-and-drop **Kanban Board** using `@hello-pangea/dnd`.
- Support uploading secure screenshot/file **Attachments** on developer-raised issues.

---

## 2. Scope & Impact

### Scope of Changes
1. **Migrations**: Create `comments`, `project_invitations`, `task_status_histories`, `payments` tables; add `attachment_path` to `issues`.
2. **Models & Observers**: Create `Comment`, `ProjectInvitation`, `TaskStatusHistory`, `Payment` Eloquent models. Create `PaymentObserver` to update `Project.paid_amount` cache.
3. **Task Status Audit Logging**: Log transitions automatically during `Task::transitionTo(...)`.
4. **Notifications**: Implement 5 custom Notifications (queueable) using database + mail channels.
5. **Invitation Flows**: Handle clicking invitations, session-stored tokens, auto-association on registration or login.
6. **Payments Management**: Create RelationManager in Filament for Payments. Redesign amount displays on Inertia page.
7. **Filament Work-Hour Reports**: Create a gorgeous dashboard widget or page summarizing project hours per month.
8. **Kanban Board**: Install `@hello-pangea/dnd` and implement a toggleable Board View on the project tasks tab, syncing dragged tasks with the status transition endpoint, respecting the state machine constraints.
9. **Issue Secure Attachments**: Allow file uploads in Filament for Issues. Add secure download route `projects.issues.download` backed by `IssuePolicy`.

### Risks & Mitigations
- **CSRF Token Issues**: Resolved already via test environmental isolation and `PreventRequestForgery` bypass.
- **State Machine Violation during Drag & Drop**: Ensure frontend disables drag handle for invalid transitions, and backend strictly validates transitions throwing a clean error.
- **Vite compilation under npm**: Ensure `@hello-pangea/dnd` package compiles smoothly with standard React/TypeScript setup.

---

## 3. Proposed Solution

### 3.1 Polymorphic Comments (`comments` table)
- **Schema**:
  - `id`: bigint, pk
  - `commentable_id`, `commentable_type`: morphs (task / issue)
  - `body`: text
  - `author_id`, `author_type`: morphs (User or Admin)
  - `timestamps`
- **Model**: `Comment` with relationships `commentable` and `author`.
- **Policy**: `CommentPolicy` allowing reading/writing only for project members and admins.

### 3.2 Task Status Change History (`task_status_histories` table)
- **Schema**:
  - `id`: bigint, pk
  - `task_id`: foreignId constrained to `tasks` (cascade delete)
  - `from_status`: string
  - `to_status`: string
  - `operator_id`, `operator_type`: morphs (User / Admin, nullable)
  - `remark`: text, nullable
  - `created_at`: timestamp (no updated_at)
- **Logic**: Triggered within `Task::transitionTo()`.

### 3.3 Project Invitations (`project_invitations` table)
- **Schema**:
  - `id`: bigint, pk
  - `project_id`: foreignId constrained to `projects`
  - `email`: string
  - `token`: string, unique
  - `expires_at`: timestamp
  - `timestamps`
- **Link**: `/projects/invite/{token}` handles logging in / registering / attaching user to project.

### 3.4 Payments Tracking (`payments` table)
- **Schema**:
  - `id`: bigint, pk
  - `project_id`: foreignId constrained to `projects`
  - `amount`: decimal(10,2)
  - `date`: date
  - `remark`: text, nullable
  - `created_by`: foreignId constrained to `admins`
  - `timestamps`
- **Observer**: `PaymentObserver` updates `projects.paid_amount` dynamically on creation, updates, or deletions.

### 3.5 Secure Issue Attachments
- **Schema**: Add `attachment_path` to `issues`.
- **Download Route**: `/projects/{project}/issues/{issue}/attachment` returning `Storage::disk('local')->download()`.

---

## 4. Implementation Plan

### Step 1: Database Migrations & Eloquent Models
1. Run migrations for `comments`, `task_status_histories`, `project_invitations`, `payments` tables and alter `issues`.
2. Generate models: `Comment`, `TaskStatusHistory`, `ProjectInvitation`, `Payment`.
3. Create `PaymentObserver` and register it in `AppServiceProvider`. Add initial migration data-sync.

### Step 2: Core Backend Logic (Controllers & Policies)
1. **CommentController**: Handles adding comments on tasks.
2. **InvitationController**: Handles invite clicks and session tracking. Update Breeze's Registration and Session controllers to resolve pending tokens.
3. **Task Status histories**: Hook into `Task::transitionTo` and save transition record.
4. **IssueAttachment Secure Route**: Implement secure downloads under `IssuePolicy`.
5. Update related policies (`CommentPolicy`, `IssuePolicy`, `ProjectInvitationPolicy`).

### Step 3: Notifications System
1. Implement 5 queueable Laravel Notifications:
   - `TaskDelegatedNotification`
   - `TaskStatusChangedNotification`
   - `NewDevLogNotification`
   - `IssueStatusChangedNotification`
   - `NewCommentNotification`
2. Integrate notifications firing into existing controller endpoints (Task status update, Task store, Dev log store, issue status transition, Comment store).

### Step 4: Filament Admin Panel Updates
1. Add **RelationManagers**:
   - `CommentsRelationManager` on `TaskResource` (and optionally `IssueResource`).
   - `PaymentsRelationManager` on `ProjectResource`.
   - `ProjectInvitationsRelationManager` (or Action) on `ProjectResource`.
2. Add **WorkHourReport Table Widget** or page on Admin Dashboard summarizing project hours per month.
3. Add file upload support for Issue attachment in `IssueResource`.

### Step 5: Inertia React Frontend Upgrades
1. Install `@hello-pangea/dnd` via npm.
2. Add **Kanban Board** to Tasks Tab in `Projects/Show.tsx`:
   - Users can drag cards across statuses.
   - Limit dragging: disable drag handle for users who don't own/delegate the transition.
   - Drop targets highlight valid columns dynamically.
3. Add **Comments Section** on `Projects/Tasks/Show.tsx` (Task details page):
   - Display a scrollable timeline of comments.
   - Textarea to submit comments.
4. Show payments list and summaries on Project details (Info tab).
5. Add attachment thumbnail/link under Issue listings with secure download support.

---

## 5. Verification & Testing

### Test Suite Additions
We will add standard feature and unit tests:
1. `CommentTest`: Test adding comments (members/admins authorized, outsiders 403).
2. `InvitationTest`: Test invitation links, expiration, auto-joining upon registration.
3. `PaymentTest`: Test creating payments updates `Project.paid_amount` correctly via observers.
4. `TaskHistoryTest`: Test task status transitions write accurate history logs.
5. `SecureIssueAttachmentTest`: Test issue screenshots cannot be downloaded by outsiders.

---

## 6. Migration & Rollback

- **Migration**: Schema migrations will include transactional data import for any existing project paid_amounts.
- **Rollback**: Standard Laravel migration rollbacks `migrate:rollback` will safely restore schemas.
