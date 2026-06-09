# API Design Guidelines — Attendance & Grading Platform

> Non-negotiable. Every endpoint, controller, service, and migration must follow this spec. Consistency is the goal.

---

## Table of Contents

1. [Authentication (Sanctum)](#1-authentication-sanctum)
2. [Request Headers](#2-request-headers)
3. [Response Envelope](#3-response-envelope)
4. [HTTP Status Codes](#4-http-status-codes)
5. [Error Format](#5-error-format)
6. [URL & Verb Conventions](#6-url--verb-conventions)
7. [Pagination, Filtering & Sorting](#7-pagination-filtering--sorting)
8. [Role Matrix](#8-role-matrix)
9. [Domain Rules Reference](#9-domain-rules-reference)
10. [Controllers](#10-controllers)
11. [Form Requests (Validation)](#11-form-requests-validation)
12. [API Resources (Output)](#12-api-resources-output)
13. [Services (Business Logic)](#13-services-business-logic)
14. [Policies (Authorization)](#14-policies-authorization)
15. [Database Guidelines](#15-database-guidelines)

---

## 1. Authentication (Sanctum)

Sanctum **API token mode** only — no cookie/session mode.

### Token Issuance

```php
$token = $user->createToken('api-token', ['*'])->plainTextToken;

return response()->json([
    'token'      => $token,
    'token_type' => 'Bearer',
]);
```

### Protected Routes

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $r) => new UserResource($r->user()));
    // all other protected routes
});
```

### Token Abilities (Optional Scoping)

```php
$user->createToken('api-token', ['students:read', 'grades:write']);
$request->user()->tokenCan('grades:write');
```

### Logout

```php
$request->user()->currentAccessToken()->delete(); // current device
$request->user()->tokens()->delete();             // all devices
```

### Public Routes (No Token Required)

```
POST /api/auth/login
POST /api/auth/forgot-password
POST /api/auth/reset-password
```

Expired accounts (`expires_at` in the past) return `401` on all requests.

---

## 2. Request Headers

| Header | Value | Required |
|---|---|---|
| `Authorization` | `Bearer {token}` | All protected endpoints |
| `Content-Type` | `application/json` | All write endpoints (except file uploads) |
| `Accept` | `application/json` | Always |

File-upload endpoints use `Content-Type: multipart/form-data`.

---

## 3. Response Envelope

Every response wraps its payload in a `data` key. No exceptions.

```json
// Single resource
{
  "data": { "id": 1, "name": "Ahmed Hassan", "email": "ahmed@example.com", "created_at": "2024-09-01T09:00:00Z" }
}

// Paginated collection
{
  "data": [{ "id": 1, "name": "Ahmed Hassan" }, { "id": 2, "name": "Sara Ali" }],
  "meta": { "current_page": 1, "last_page": 4, "per_page": 15, "total": 60 },
  "links": { "first": "/api/students?page=1", "last": "/api/students?page=4", "prev": null, "next": "/api/students?page=2" }
}
```

---

## 4. HTTP Status Codes

| Situation | Code |
|---|---|
| Successful GET / PATCH | `200 OK` |
| Successful POST | `201 Created` |
| Successful DELETE / no-body action | `204 No Content` |
| Validation failed | `422 Unprocessable Entity` |
| Missing / invalid / expired token | `401 Unauthorized` |
| Valid token, insufficient role | `403 Forbidden` |
| Resource not found | `404 Not Found` |
| Rate limit exceeded | `429 Too Many Requests` |
| Unhandled server exception | `500 Internal Server Error` |

---

## 5. Error Format

```json
// 401
{ "message": "Unauthenticated." }

// 403
{ "message": "This action is unauthorized." }

// 404
{ "message": "Resource not found." }

// 422
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required.", "The email must be a valid email address."],
    "role":  ["The selected role is invalid."]
  }
}
```

---

## 6. URL & Verb Conventions

**Resource-based, plural noun URLs. Never put verbs in URLs.**

| Action | Method | URL |
|---|---|---|
| List all | `GET` | `/api/students` |
| Show one | `GET` | `/api/students/{id}` |
| Create | `POST` | `/api/students` |
| Full replace | `PUT` | `/api/students/{id}` |
| Partial update | `PATCH` | `/api/students/{id}` |
| Delete | `DELETE` | `/api/students/{id}` |

**Nested resources — max 2 levels deep:**
```
GET  /api/courses/{course}/students
POST /api/courses/{course}/attendance
GET  /api/students/{student}/grades
```

**Actions that don't map to CRUD — sub-resource noun, not a verb:**
```
POST /api/attendance/{id}/approve    ✅
POST /api/approveAttendance/{id}     ❌
```

---

## 7. Pagination, Filtering & Sorting

Always paginate. Never return unbounded lists.

| Param | Type | Default | Max | Description |
|---|---|---|---|---|
| `page` | integer | `1` | — | Page number |
| `per_page` | integer | `15` | `100` | Items per page |
| `search` | string | — | — | Full-text search |
| `sort` | string | — | — | Column to sort by |
| `direction` | string | `asc` | — | `asc` or `desc` |

```php
$students = Student::query()
    ->when($request->filled('search'), fn ($q) =>
        $q->where('name', 'ilike', "%{$request->search}%")
          ->orWhere('email', 'ilike', "%{$request->search}%")
    )
    ->when($request->filled('sort'), fn ($q) =>
        $q->orderBy($request->sort, $request->get('direction', 'asc'))
    )
    ->with('courses')
    ->paginate($request->get('per_page', 15));

return StudentResource::collection($students)->response();
```

---

## 8. Role Matrix

| Action | `branch_manager` | `track_admin` | `instructor` | `student` |
|---|:-:|:-:|:-:|:-:|
| Provision users | ✅ | ✅ own track | ❌ | ❌ |
| Create tracks / cohorts | ✅ | ❌ | ❌ | ❌ |
| Configure courses, lab groups | ❌ | ✅ | ❌ | ❌ |
| Schedule engagements | ❌ | ✅ | ❌ | ❌ |
| Record attendance | ❌ | ❌ | ✅ own group | ❌ |
| Grade submissions | ❌ | ✅ override | ✅ own group | ❌ |
| Submit work | ❌ | ❌ | ❌ | ✅ |
| Approve excuses | ❌ | ✅ | ❌ | ❌ |
| Post announcements | ❌ | ✅ any time | ✅ active window | ❌ |
| View branch analytics | ✅ | ❌ | ❌ | ❌ |
| View cohort analytics | ❌ | ✅ | ❌ | ❌ |
| View billing rollup | ✅ | ❌ | ❌ | ❌ |
| View own data | ✅ | ✅ | ✅ | ✅ |

---

## 9. Domain Rules Reference

| ID | Rule |
|---|---|
| **LC-1** | A track may have at most one active cohort at any time. |
| **LC-2** | Only the Branch Manager creates cohorts and assigns Track Admins. |
| **ACC-1** | Branch Manager sees branch-wide analytics with drill-down into any track. |
| **ACC-2** | Track Admin sees the full roster and all grades for their own cohort. |
| **ACC-3** | Instructor sees only the lab group(s) they are assigned to. |
| **ACC-4** | Student sees only their own data — no peer data. |
| **ACC-5** | Student tags/notes are visible to every person who grades that student. |
| **ENG-2** | Late lab penalty: −25% per full day, reaches 0 after 4 days. |
| **ENG-5** | Instructor account access is limited to their engagement's date range. |
| **ATT-3** | Business-session attendance is recorded per track even for shared sessions. |
| **ATT-4** | Every student's attendance ledger starts at 250 points. |
| **ATT-5** | Unexcused absence: −25 pts. Approved excuse: −5 pts. |
| **ATT-6** | Missing any session type (including business sessions) affects the ledger. |
| **EXC-1** | Student submits excuse with reason + optional attachment. |
| **EXC-2** | Attachment ≤ 1 MB, PDF or image only. |
| **EXC-3** | Track Admin approves or rejects. Approval changes deduction 25 → 5. |
| **GRD-1** | A course is scored out of 100. |
| **GRD-2** | Component weights are set once per cohort by the Track Admin. |
| **GRD-3** | `normalized_score = (raw ÷ max) × weight` |
| **GRD-4** | Each lab instructor grades only their assigned group. |
| **GRD-5** | Track Admin enters final exam/project grades. |
| **GRD-6** | Track Admin overrides require a mandatory note; original value is kept for audit. |
| **GRD-7** | Predefined tags and free-text notes on students. |
| **GRD-8** | Tags and notes accumulate across courses, visible to all graders. |
| **POR-4** | Students submit work as a URL or a direct file upload. |
| **BIL-1** | Billable hours are auto-calculated from the schedule — never entered manually. |
| **BIL-2** | External: hours × rate. Internal: hours × rate on top of fixed salary. |
| **BIL-3** | System produces a consolidated billing rollup. |
| **BIL-4** | Branch Manager sees the rollup with internal/external split. |
| **ANL-1** | At-risk: attendance balance < 150 OR any course grade < 60. |
| **ANN-1** | Track Admin posts announcements at any time. |
| **ANN-2** | Instructor posts announcements only during active engagement window. |
| **SEC-1** | No self-registration. Accounts provisioned top-down. |
| **SEC-2** | Every account has an `expires_at`. Expired accounts cannot log in. |
| **SEC-3** | Authorization is enforced server-side on every endpoint. |
| **SEC-4** | File uploads are validated for size and type. |

---

## 10. Controllers

**Thin — they orchestrate, not implement.** Business logic lives in Services.

```php
// ✅ Correct
class AttendanceController extends Controller
{
    public function store(StoreAttendanceRequest $request, AttendanceService $service): JsonResponse
    {
        $record = $service->record($request->validated(), $request->user());

        return (new AttendanceResource($record))
            ->response()
            ->setStatusCode(201);
    }
}

// ❌ Wrong — fat controller with inline logic
class AttendanceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // validation, DB queries, business rules, emails all in here
        return response()->json($result);
    }
}
```

**Use single-action controllers for complex or standalone operations:**

```php
// app/Http/Controllers/Api/GenerateReportController.php
class GenerateReportController extends Controller
{
    public function __invoke(GenerateReportRequest $request, ReportService $service): JsonResponse
    {
        return response()->json($service->generate($request->validated()));
    }
}

// routes/api.php
Route::post('/reports/generate', GenerateReportController::class);
```

---

## 11. Form Requests (Validation)

**Never validate inside a controller.** Every endpoint that accepts input must have its own Form Request class.

```php
// app/Http/Requests/Attendance/StoreAttendanceRequest.php
class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization belongs here, not in the controller
        return $this->user()->can('create', Attendance::class);
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'integer', 'exists:course_sessions,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'status'     => ['required', 'in:present,absent,late,excused'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: present, absent, late, excused.',
        ];
    }
}
```

---

## 12. API Resources (Output)

**Never return raw Eloquent models.** Every response must go through an API Resource.

```php
// app/Http/Resources/StudentResource.php
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'student_id' => $this->student_id,
            // whenLoaded() prevents N+1 — only included if eager-loaded
            'courses'    => CourseResource::collection($this->whenLoaded('courses')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}

// Paginated — wraps automatically in "data"
return StudentResource::collection(Student::paginate(15));

// Single
return new StudentResource($student);
```

---

## 13. Services (Business Logic)

**Plain PHP classes, no HTTP concerns.** Logic that spans multiple models, touches multiple tables, or needs to be reused belongs here.

```php
// app/Services/AttendanceService.php
class AttendanceService
{
    public function record(array $data, User $instructor): Attendance
    {
        $session = CourseSession::findOrFail($data['session_id']);

        if ($session->instructor_id !== $instructor->id) {
            throw new UnauthorizedException('You do not own this session.');
        }

        if ($session->date->isPast()) {
            throw new AttendanceException('Cannot record attendance for a past session.');
        }

        return DB::transaction(function () use ($data, $session) {
            return Attendance::create([
                'session_id'  => $session->id,
                'student_id'  => $data['student_id'],
                'status'      => $data['status'],
                'recorded_at' => now(),
            ]);
        });
    }
}
```

---

## 14. Policies (Authorization)

**Use Laravel Policies for all permission checks.** Never hardcode role strings in controllers.

```php
// app/Policies/GradePolicy.php
class GradePolicy
{
    public function update(User $user, Grade $grade): bool
    {
        return $user->hasRole('instructor')
            && $grade->course->instructor_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'instructor']);
    }
}

// In a controller
$this->authorize('update', $grade);

// In a Form Request
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('grade'));
}
```

---

## 15. Database Guidelines

### Eager Load — Prevent N+1

```php
// ✅
$courses = Course::with(['students', 'instructor', 'sessions'])->paginate(15);

// ❌ Never query inside a loop
foreach (Course::all() as $course) {
    echo $course->instructor->name; // 1 query per course
}
```

### Query Scopes

```php
// app/Models/Student.php
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', 'active');
}

Student::active()->with('courses')->paginate(15);
```

### Transactions

```php
DB::transaction(function () use ($data) {
    $student = Student::create($data['student']);
    $student->courses()->attach($data['course_ids']);
});
```

### Migration Conventions

```php
Schema::create('attendance', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_id')->constrained()->cascadeOnDelete();
    $table->foreignId('session_id')->constrained('course_sessions')->cascadeOnDelete();
    $table->enum('status', ['present', 'absent', 'late', 'excused']);
    $table->text('notes')->nullable();
    $table->timestamp('recorded_at');
    $table->timestamps();

    $table->unique(['student_id', 'session_id']); // prevent duplicates
    $table->index(['session_id', 'status']);       // index common query patterns
});
```

**Rules:**
- Always `cascadeOnDelete` on foreign keys
- Composite unique constraints to prevent duplicate records
- Index columns used in `WHERE` and `ORDER BY`
- Use `enum` for finite status fields
- `nullable()` only when a field is genuinely optional