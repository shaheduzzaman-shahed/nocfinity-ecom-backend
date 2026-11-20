<laravel-boost-guidelines>
=== .ai/guideline rules ===

# **Laravel Backend Architecture & Best Practices**

For your fashion e-commerce platform, I recommend building Laravel as a **modular, service-oriented REST API**.

Keep Laravel responsible for all business logic, authentication, authorization, pricing, inventory, checkout, payments, and order processing. Next.js should primarily handle the user interface and user interactions.

The most important architectural decision: **organize the backend by business modules, not just by Laravel's default technical folders.**

## **1. Recommended Laravel project structure**

Keep Laravel's standard structure, but organize business logic into dedicated modules.

```

fashion-ecommerce-backend/
│
├── app/
│   ├── Actions/
│   │   ├── Auth/
│   │   ├── Products/
│   │   ├── Cart/
│   │   ├── Checkout/
│   │   ├── Payments/
│   │   └── Orders/
│   │
│   ├── Enums/
│   │   ├── OrderStatus.php
│   │   ├── PaymentStatus.php
│   │   └── UserType.php
│   │
│   ├── Events/
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/
│   │   │           ├── Auth/
│   │   │           ├── Admin/
│   │   │           ├── Customer/
│   │   │           ├── ProductController.php
│   │   │           ├── CategoryController.php
│   │   │           ├── CartController.php
│   │   │           ├── CheckoutController.php
│   │   │           └── PaymentController.php
│   │   │
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   │   └── Api/
│   │   │       └── V1/
│   │   └── Resources/
│   │       └── Api/
│   │           └── V1/
│   │
│   ├── Jobs/
│   ├── Listeners/
│   ├── Mail/
│   ├── Models/
│   ├── Notifications/
│   ├── Policies/
│   ├── Providers/
│   ├── Services/
│   │   ├── Payment/
│   │   ├── Inventory/
│   │   ├── Order/
│   │   ├── Pricing/
│   │   └── Shipping/
│   │
│   └── Support/
│
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
│
├── routes/
│   ├── api.php
│   ├── web.php
│   └── console.php
│
├── storage/
├── tests/
│   ├── Feature/
│   │   ├── Auth/
│   │   ├── Products/
│   │   ├── Cart/
│   │   ├── Checkout/
│   │   ├── Payments/
│   │   ├── Orders/
│   │   └── Admin/
│   │
│   └── Unit/
│
├── .env
├── .env.example
├── artisan
└── composer.json

```

This is a logical organization, not a requirement to create every folder immediately. Add folders when the relevant module is implemented.

## **2. Use a clean request-to-response architecture**

Every API request should follow a predictable flow.

**API Request**

POST /api/v1/admin/products

**Route & Middleware**

Authentication · Permissions · Rate limiting

**Form Request**

Validation and authorization

**Controller**

Receives request and coordinates action

**Action / Service**

Business logic and database operations

**Model / Database**

Eloquent · Transactions · Relationships

**API Resource Response**

Consistent JSON response

### **Example: Product creation**

- `ProductController`: receives the request.
- `StoreProductRequest`: validates product information.
- `CreateProductAction`: handles product creation.
- `Product`: Eloquent model.
- `ProductResource`: formats the API response.

Keep controllers thin. Avoid putting all business logic directly inside controller methods.

## **3. Organize the backend by business modules**

These are the main modules I recommend for your application.

| Module              | Main responsibilities                         |
| ------------------- | --------------------------------------------- |
| Auth                | Registration, login, logout, password reset   |
| Users               | Customer profiles and accounts                |
| Staff               | Staff account management                      |
| Roles & Permissions | RBAC and access control                       |
| Categories          | Category CRUD                                 |
| Products            | Product CRUD, images, stock                   |
| Cart                | Cart and cart items                           |
| Coupons             | Coupon validation and management              |
| Shipping            | Shipping configuration                        |
| Currency            | Store currency configuration                  |
| Checkout            | Checkout validation and pricing               |
| Payments            | Stripe payment and webhooks                   |
| Orders              | Order creation, history, status, cancellation |
| Inventory           | Stock deduction and restoration               |
| Customers           | Admin customer management                     |
| Dashboard           | Statistics and summaries                      |
| Notifications       | Email and order notifications                 |

For each module, separate its:

- Controller
- Form Requests
- Models (where applicable)
- Actions or Services
- API Resources
- Policies
- Tests

Do not create unnecessary abstractions for simple CRUD operations.

## **4. Follow Laravel's best practices for each layer**

### **A. Controllers**

Controllers should coordinate requests, not contain the entire business process. Do not write business logics in controller. Use service or action class.

Good:

```
public function store(StoreProductRequest $request)
{
    $product = app(CreateProductAction::class)
        ->execute($request->validated());

    return new ProductResource($product);
}
```

Avoid putting image processing, database transactions, stock calculations, and validation rules into one large controller method.

### **B. Form Requests**

Use dedicated Form Request classes for validation.

Example:

```
class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'sku' => ['required', 'string', 'unique:products,sku'],
            'stock' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

Best practices:

- Keep validation out of controllers.
- Use separate requests for create and update.
- Validate uploaded files.
- Validate nested arrays.
- Use authorization checks for sensitive requests.
- Keep frontend and backend validation consistent, but trust only backend validation.

### **C. Models and Eloquent relationships**

Use relationships instead of repeatedly writing manual database joins.

Example:

```
class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'description',
        'price',
        'stock',
        'status',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}
```

Best practices:

- Use `$fillable` or carefully controlled mass assignment.
- Define relationships clearly.
- Use casts for dates, booleans, and structured data.
- Use enums for fixed status values.
- Avoid putting complicated business workflows inside model observers.
- Use database constraints in addition to application validation.

### **D. Services and Actions**

Use Actions for individual business operations and Services for reusable business capabilities.

Examples:

| Action / Service         | Responsibility                   |
| ------------------------ | -------------------------------- |
| CreateProductAction      | Create product                   |
| UpdateProductAction      | Update product                   |
| CalculateCartTotalAction | Calculate cart summary           |
| ApplyCouponAction        | Validate and apply coupon        |
| CreateOrderAction        | Create order                     |
| ProcessPaymentAction     | Handle payment workflow          |
| DeductStockAction        | Deduct inventory                 |
| RestoreStockAction       | Restore inventory                |
| ShippingService          | Retrieve applicable shipping fee |

Do not create a generic service for every single database query. Keep the architecture simple.

### **E. API Resources**

Always use Laravel API Resources to control the JSON response.

Example:

```
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'price' => $this->price,
            'stock' => $this->stock,
            'category' => new CategoryResource(
                $this->whenLoaded('category')
            ),
            'images' => ProductImageResource::collection(
                $this->whenLoaded('images')
            ),
        ];
    }
}
```

Benefits:

- Consistent API responses.
- Avoid exposing private database fields.
- Control nested relationships.
- Easier Next.js integration.
- Easier API versioning.

## **5. API design standards**

Use RESTful routes and version all endpoints.

Example:

```
Route::prefix('v1')->group(function () {

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product:slug}', [ProductController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/items', [CartController::class, 'store']);

    });

    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'staff'])
        ->group(function () {

            Route::apiResource('products', AdminProductController::class);

        });

});
```

Recommended conventions:

- `GET` for retrieving.
- `POST` for creating.
- `PUT` or `PATCH` for updating.
- `DELETE` for deleting.
- Use plural resource names.
- Use consistent HTTP status codes.
- Use pagination for listing endpoints.
- Use filtering and sorting query parameters.
- Use API Resources for output.

### **Standard response structure**

Success:

```
{
  "success": true,
  "message": "Product retrieved successfully",
  "data": {
    "id": 1,
    "name": "Cotton Shirt",
    "price": "1200.00"
  }
}
```

Error:

```
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": [
      "The name field is required."
    ]
  }
}
```

Choose one response format and use it consistently across the API.

## **6. Authentication and authorization**

For your Laravel Sanctum application, implement authentication carefully.

### **Customer and staff authentication**

- Use Sanctum for API authentication.
- Keep customer and staff authorization logically separate.
- Define staff access middleware.
- Protect admin routes.
- Use policies for resource-level authorization.
- Never rely exclusively on frontend permission checks.

### **RBAC structure**

Recommended tables:

- `users`
- `roles`
- `permissions`
- `role_user`
- `permission_role`

Example permissions:

```
products.view
products.create
products.update
products.delete

orders.view
orders.update
orders.cancel

customers.view
customers.manage

roles.view
roles.manage

shipping.view
shipping.manage
```

Use Laravel Gates or Policies where appropriate, with middleware for route-level checks.

**Important:** A Product Manager should not be able to update orders simply because they can access the admin panel.

## **7. Database and inventory best practices**

This is particularly important for e-commerce.

### **Database rules**

- Use migrations for every schema change.
- Define foreign keys.
- Add indexes for common filters.
- Add unique constraints for SKU, order number, and coupon code where applicable.
- Use decimal database columns for monetary amounts.
- Use appropriate column types for currency codes and statuses.
- Avoid unnecessary database queries.
- Use eager loading to prevent N+1 queries.

### **Inventory management**

Never update stock using an unprotected read-then-write operation.

Use transactions and row locks:

```
DB::transaction(function () use ($productId, $quantity) {

    $product = Product::query()
        ->lockForUpdate()
        ->findOrFail($productId);

    if ($product->stock < $quantity) {
        throw new InsufficientStockException();
    }

    $product->decrement('stock', $quantity);

});
```

For your agreed business rules:

- Pending order: stock is not deducted.
- Successful payment: stock is deducted.
- Cancelled unpaid order: no stock restoration.
- Cancelled paid order: restore stock exactly once.
- Processing, Shipped, and Delivered orders: cancellation rules must be enforced.
- Duplicate payment webhooks must not deduct stock twice.

Use database transactions for the complete order/inventory operation and define how failed or delayed payment events are handled.

## **8. Payment and order processing best practices**

Stripe integration deserves its own isolated module.

Recommended architecture:

```
PaymentController
       |
       v
PaymentService
       |
       v
StripeService
       |
       v
Stripe API
       |
       v
Stripe Webhook
       |
       v
PaymentSucceededAction
       |
       v
Order + Inventory
```

Important rules:

1. Never trust payment success from the frontend.
2. Verify Stripe webhook signatures.
3. Use idempotency to prevent duplicate processing.
4. Store Stripe transaction references.
5. Keep payment status and order status separate.
6. Store historical product prices and order totals.
7. Use transactions for order and inventory changes.
8. Preserve financial history after cancellation.
9. Do not implement automatic refunds unless the project scope is changed.

Use integer minor units when communicating monetary amounts to Stripe where required by its API, and handle currency-specific minor-unit rules correctly.

## **9. Product image upload and file storage**

Do not store uploaded image files directly inside the database.

Use Laravel's filesystem.

Recommended approach:

- Validate MIME type.
- Validate file size.
- Generate safe filenames.
- Store images using Laravel Storage.
- Save image paths in `product_images`.
- Use a consistent image storage disk.
- Delete old files when images are replaced or removed.
- Avoid exposing private files publicly.
- Configure production storage and backups.

For fashion products, keep product images organized and support multiple images per product.

## **10. Queue and background jobs**

Use Laravel queues for tasks that do not need to block the customer's request.

Recommended jobs:

- Send registration email.
- Send order confirmation email.
- Send payment confirmation email.
- Send shipping notification.
- Send cancellation notification.
- Process non-critical notifications.
- Generate activity log records where appropriate.

Example:

```
SendOrderConfirmationEmail::dispatch($order);
```

Use a reliable queue driver in production and configure failed-job handling.

Do not move critical stock updates or payment confirmation into an asynchronous job without designing the consistency and retry behavior carefully.

## **11. Security best practices**

Security should be built into every module.

| Security area  | Recommended practice                                        |
| -------------- | ----------------------------------------------------------- |
| Authentication | Sanctum                                                     |
| Authorization  | Policies, Gates, middleware                                 |
| Passwords      | Laravel hashing                                             |
| Validation     | Form Requests                                               |
| SQL injection  | Eloquent and parameterized queries                          |
| XSS            | Escape output                                               |
| CSRF           | Sanctum CSRF protection for cookie-based SPA authentication |
| Rate limiting  | Login and sensitive endpoints                               |
| File uploads   | MIME, size, and content validation                          |
| IDOR           | Ownership and permission checks                             |
| Payment        | Stripe webhook signature verification                       |
| Secrets        | Environment variables                                       |
| API exposure   | API Resources and controlled fields                         |
| Admin security | Strict staff authorization                                  |

Also:

- Never expose `.env` values.
- Never expose Stripe secret keys to Next.js.
- Avoid logging passwords, tokens, or payment secrets.
- Use HTTPS in production.
- Keep Laravel and Composer dependencies updated.
- Configure secure response headers.
- Protect destructive administrative actions.

## **12. Testing strategy**

Use PHPUnit or Pest for backend testing.

Recommended testing structure:

```
tests/
├── Feature/
│   ├── Auth/
│   ├── Products/
│   ├── Categories/
│   ├── Cart/
│   ├── Coupons/
│   ├── Checkout/
│   ├── Payments/
│   ├── Orders/
│   ├── Inventory/
│   ├── Customers/
│   └── Admin/
│
└── Unit/
    ├── Services/
    └── Actions/
```

Test the following:

- Registration and login.
- Customer/staff access separation.
- Role and permission restrictions.
- Product CRUD.
- Category CRUD.
- Cart calculations.
- Coupon validation.
- Shipping configuration.
- Currency configuration.
- Checkout validation.
- Stripe webhook verification.
- Duplicate webhook processing.
- Inventory deduction.
- Inventory restoration.
- Order cancellation.
- Customer order ownership.
- Historical order snapshots.

**Highest priority:** payment, inventory, authorization, and order lifecycle tests.

## **13. Performance best practices**

- Use eager loading with `with()`.
- Avoid N+1 queries.
- Add database indexes.
- Paginate large collections.
- Cache public category and product data where appropriate.
- Invalidate cache after product or category updates.
- Avoid caching customer-specific data across users.
- Use queues for email.
- Optimize uploaded product images.
- Use appropriate database query scopes.
- Monitor slow queries.
- Use Laravel's query logging and profiling tools in development.

## **14. Recommended coding standards**

Follow these conventions consistently.

| Item        | Convention              | Example               |
| ----------- | ----------------------- | --------------------- |
| Models      | Singular PascalCase     | `Product`             |
| Controllers | PascalCase + Controller | `ProductController`   |
| Requests    | Action + Request        | `StoreProductRequest` |
| Resources   | Model + Resource        | `ProductResource`     |
| Services    | Name + Service          | `PaymentService`      |
| Actions     | Verb + Model + Action   | `CreateOrderAction`   |
| Jobs        | Action + Job            | `SendOrderEmailJob`   |
| Policies    | Model + Policy          | `ProductPolicy`       |
| Enums       | PascalCase              | `OrderStatus`         |
| Tables      | Plural snake_case       | `order_items`         |
| Columns     | snake_case              | `created_at`          |
| API paths   | Plural kebab-case       | `/api/v1/products`    |

Use Laravel Pint to maintain consistent PHP formatting.

## **15. Recommended Laravel development sequence**

Build your backend in this order:

| Step | Module                                 |
| ---- | -------------------------------------- |
| 1    | Laravel project setup                  |
| 2    | Environment and database configuration |
| 3    | Database architecture and migrations   |
| 4    | API versioning and response structure  |
| 5    | Customer authentication                |
| 6    | Staff authentication                   |
| 7    | Roles and permissions                  |
| 8    | Category management                    |
| 9    | Product management                     |
| 10   | Product image management               |
| 11   | Product search, filters, sorting       |
| 12   | Shopping cart                          |
| 13   | Coupon management                      |
| 14   | Shipping configuration                 |
| 15   | Currency configuration                 |
| 16   | Pricing and checkout                   |
| 17   | Stripe integration                     |
| 18   | Order creation                         |
| 19   | Inventory management                   |
| 20   | Customer order management              |
| 21   | Admin order management                 |
| 22   | Customer management                    |
| 23   | Staff management                       |
| 24   | Dashboard APIs                         |
| 25   | Email notifications                    |
| 26   | Activity logging                       |
| 27   | Security review                        |
| 28   | Automated testing                      |
| 29   | API documentation                      |
| 30   | Production deployment                  |

## **16. My recommended architecture decisions**

**Laravel architecture**

Recommended

# **Modular monolith**

One Laravel application, organized into independent business modules.

**Controllers**

Thin controllers responsible for HTTP requests and responses.

**Business logic**

Actions for individual operations and Services for reusable capabilities.

**Validation**

Dedicated Form Request classes.

**Authorization**

Sanctum + middleware + Policies/Gates.

**Database**

Eloquent + migrations + transactions + row locks.

**API responses**

Laravel API Resources.

**Background processing**

Laravel Queues and Jobs.

**Testing**

Pest/PHPUnit + Feature and Unit tests.

## **17. Common mistakes to avoid**

| Don't do this                        | Do this instead                     |
| ------------------------------------ | ----------------------------------- |
| Put all logic in controllers         | Use Actions and Services            |
| Put every query in separate services | Use Eloquent and query scopes       |
| Trust frontend prices                | Calculate prices on backend         |
| Trust frontend permissions           | Verify permissions in Laravel       |
| Deduct stock without locking         | Use transactions and row locks      |
| Process Stripe webhooks repeatedly   | Implement idempotency               |
| Store monetary values as floats      | Use decimal/integer representations |
| Return entire Eloquent models        | Use API Resources                   |
| Load all products at once            | Use pagination                      |
| Send emails synchronously            | Use queues                          |
| Use one giant route file             | Group routes by module              |
| Build microservices immediately      | Start with a modular monolith       |

## **Final recommendation**

For this project, I would use a **Laravel modular monolith with Actions + Services + Form Requests + API Resources + Policies**.

You do not need microservices, complicated repository patterns, or an enterprise-level architecture at the beginning. A clean, well-tested Laravel application is more valuable than unnecessary architectural complexity.

**The most important modules to get right are Products, Cart, Checkout, Payments, Orders, Inventory, and RBAC.** These modules directly affect the accuracy, security, and reliability of the store.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
