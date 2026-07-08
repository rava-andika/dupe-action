<?php

use App\Jobs\LogAdminActivity;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adding similar route function but automatically add locale to the param
 *
 * @return string
 */
if (! function_exists('to')) {
    function to(string $name, array $parameters = [], bool $absolute = true): string
    {
        $route = Route::getRoutes()->getByName($name);
        $requiresLocale = in_array('locale', $route?->parameterNames() ?? []);

        // If locale is required, add it to the parameters
        if ($requiresLocale) {
            $locale = app()->getLocale();
            $parameters = array_merge(['locale' => $locale], $parameters);
        }

        return app('url')->route($name, $parameters, $absolute);
    };
}

/**
 * Adding similar redirect to function but automatically add locale to the param
 *
 * @return RedirectResponse
 */
if (! function_exists('go_to')) {
    function go_to(string $name, array $parameters = [], bool $absolute = true): RedirectResponse
    {
        return redirect(to($name, $parameters, $absolute));
    }
}

/**
 * Makes an HTTP request using cURL for better performance compared to PHP's built-in HTTP functions.
 *
 * @param  bool  $isPost  Whether to use POST (true) or GET (false) method.
 * @param  string  $url  The target URL for the HTTP request.
 * @param  string|null  $headerKey  Optional authorization or custom header key (e.g. Bearer token or API key).
 * @param  bool  $encoding  Whether to enable response encoding (e.g. gzip).
 * @param  array|null  $payload  Data to send with the request if using POST. Ignored for GET.
 * @return array [$body, $err, $http_code, $headerValue]
 *               - $body: The response body returned by the server
 *               - $err: Any error message encountered during the request (empty if none)
 *               - $http_code: The HTTP status code returned by the server
 *               - $headerValue: Parsed or raw headers from the response, depending on implementation
 */
if (! function_exists('curlRequest')) {
    function curlRequest(
        bool $isPost,
        string $url,
        ?string $headerKey = null,
        bool $encoding = false,
        ?array $payload = null
    ): array {
        $curl = curl_init();
        $headerValue = null;

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_ENCODING => $encoding ? '' : 'identity',
            CURLOPT_RETURNTRANSFER => true,  // Ensure response is returned as a string
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept-Language: en-US,en;q=0.5',
            ], // Include headers in the request
        ];

        if ($headerKey) {
            $curlOptions[CURLOPT_HEADERFUNCTION] = function ($curl, $header) use (&$headerValue, $headerKey) {
                if (stripos($header, $headerKey.':') === 0) {
                    $headerValue = trim(substr($header, strlen($headerKey) + 1));
                }

                return strlen($header);
            };
        }

        if ($isPost) {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($payload);
            $curlOptions[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        curl_setopt_array($curl, $curlOptions);

        $body = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        return [$body, $err, $http_code, $headerValue];
    }
}

/**
 * Checking if the turnstile is valid
 *
 * @return bool
 */
if (! function_exists('checkTurnstile')) {
    function checkTurnstile(?string $token = null): bool
    {
        // If there's a custom version bound in the container, use it (for testing)
        if (app()->bound('checkTurnstile')) {
            return app('checkTurnstile')($token);
        }

        [$body, $err, $http_code] = curlRequest(
            isPost: true,
            url: 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            payload: [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
            ]
        );
        if ($err || $http_code != 200) {
            Log::error("Turnstile error: $err | $http_code");

            return false;
        }

        return json_decode($body)->success;
    }
}

/**
 * give paginate page for all the resources controller
 */
if (! function_exists('getPaginatedData')) {
    /**
     * Handles server-side pagination, sorting, and searching for a model or query.
     */
    function getPaginatedData(
        Request $request,
        string|Builder $modelOrQuery,
        array $allowedColumns,
        string $routeName,
        ?Closure $filterFn = null,
        array $defaultAppends = []
    ): array {
        // Get and validate request inputs
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $sortColumn = $request->input('tableSortColumn');
        $sortDirection = strtolower($request->input('tableSortDirection'));
        $onlyDeleted = $request->boolean('onlyDeleted');
        $deletedRecordsCount = 0; // Initialize deleted records count

        if ($sortColumn && ! in_array($sortDirection, ['asc', 'desc'])) {
            abort(400, 'Invalid sort direction.');
        }

        // Prepare the base query builder
        $builder = is_string($modelOrQuery) ? $modelOrQuery::query() : clone $modelOrQuery;
        $modelInstance = $builder->getModel();
        $baseTable = $builder->getModel()->getTable();

        // Check if the model uses the SoftDeletes trait to avoid errors
        if (in_array(SoftDeletes::class, class_uses_recursive($modelInstance))) {
            $deletedRecordsCount = (clone $builder)->onlyTrashed()->count();
            $builder->when($onlyDeleted, function ($query) {
                $query->onlyTrashed();
            });
        } elseif ($onlyDeleted) {
            // If the model doesn't use SoftDeletes, return an empty paginator
            return [
                'paginator' => new LengthAwarePaginator([], 0, $perPage, 1),
                'tableSortColumn' => $sortColumn,
                'tableSortDirection' => $sortDirection,
                'search' => $search,
                'onlyDeleted' => $onlyDeleted,
                'allIds' => [],
                'deletedRecordsCount' => $deletedRecordsCount,
            ];
        }

        $selects = [];
        $searchableWhere = []; // For standard WHERE clauses
        $searchableHaving = []; // For aggregated HAVING clauses
        $sortableAliases = [];
        $joinedRelations = [];
        $isGrouped = false;
        $allIds = (clone $builder)
            ->pluck("{$baseTable}.id")
            ->all();

        foreach ($allowedColumns as $column) {
            if (str_contains($column, '.')) {
                // Handle relations
                [$relationName, $field] = explode('.', $column, 2);

                if (method_exists($modelInstance, $relationName) && ! isset($joinedRelations[$relationName])) {
                    $relation = $modelInstance->$relationName();
                    $selectAlias = "{$relationName}_{$field}";

                    if ($relation instanceof BelongsTo) {
                        // BelongsTo (One-to-One) Handling
                        $relatedTable = $relation->getRelated()->getTable();
                        $foreignKey = $relation->getForeignKeyName();
                        $ownerKey = $relation->getOwnerKeyName();
                        $joinAlias = $relationName;

                        $builder->leftJoin("{$relatedTable} as {$joinAlias}", "{$baseTable}.{$foreignKey}", '=', "{$joinAlias}.{$ownerKey}");
                        $selects[] = "{$joinAlias}.{$field} as {$selectAlias}";
                        $searchableWhere[] = "{$joinAlias}.{$field}";
                    } elseif ($relation instanceof BelongsToMany) {
                        // BelongsToMany (Many-to-Many or One-to-Many) Handling
                        $pivotTable = $relation->getTable();
                        $relatedTable = $relation->getRelated()->getTable();
                        $foreignPivotKey = $relation->getForeignPivotKeyName();
                        $relatedPivotKey = $relation->getRelatedPivotKeyName();
                        $relatedKeyName = $relation->getRelatedKeyName();

                        // Join pivot table and then the related table
                        $builder->leftJoin($pivotTable, "{$baseTable}.id", '=', "{$pivotTable}.{$foreignPivotKey}");
                        $builder->leftJoin($relatedTable, "{$pivotTable}.{$relatedPivotKey}", '=', "{$relatedTable}.{$relatedKeyName}");

                        // Use GROUP_CONCAT to aggregate results into a single string
                        $selects[] = DB::raw("GROUP_CONCAT(DISTINCT {$relatedTable}.{$field} SEPARATOR ', ') as {$selectAlias}");
                        $searchableHaving[] = $selectAlias; // Search on the aggregated result
                        $isGrouped = true;
                    }

                    $joinedRelations[$relationName] = true;
                    $sortableAliases[$selectAlias] = $column;
                }
            } else {
                // No change for base table columns
                $selects[] = "{$baseTable}.{$column}";
                $searchableWhere[] = "{$baseTable}.{$column}";
                $sortableAliases[$column] = $column;
            }
        }

        if ($isGrouped) {
            $builder->groupBy($searchableWhere);
        }

        if ($sortColumn && ! isset($sortableAliases[$sortColumn])) {
            abort(400, 'Invalid sort column.');
        }

        $query = $filterFn ? $filterFn($builder, $baseTable) : $builder;
        $query->select($selects);

        if ($search) {
            $query->where(function ($q) use ($search, $searchableWhere, $searchableHaving) {
                // Apply standard WHERE searches
                foreach ($searchableWhere as $col) {
                    $q->orWhere($col, 'LIKE', "%{$search}%");
                }
                // Apply HAVING searches for aggregated columns
                foreach ($searchableHaving as $col) {
                    $q->orHaving($col, 'LIKE', "%{$search}%");
                }
            });
        }

        if ($sortColumn) {
            $query->orderBy($sortColumn, $sortDirection);
        }

        // Build the list of parameters to append to pagination links
        $appends = array_filter([
            'per_page' => $perPage,
            'search' => $search,
            'tableSortColumn' => $sortColumn,
            'tableSortDirection' => $sortDirection,
            'onlyDeleted' => $onlyDeleted,
        ]);
        $appends = array_merge($defaultAppends, $appends);

        // Paginate the results
        if ($perPage === 'all') {
            $items = $query->get();
            $paginator = new LengthAwarePaginator($items, $items->count(), -1, 1);
        } else {
            $paginator = $query->paginate((int) $perPage);
        }

        $paginator->withPath(to($routeName))->appends($appends);

        return [
            'paginator' => $paginator,
            'tableSortColumn' => $sortColumn,
            'tableSortDirection' => $sortDirection,
            'search' => $search,
            'onlyDeleted' => $onlyDeleted,
            'allIds' => $allIds,
            'deletedRecordsCount' => $deletedRecordsCount,
        ];
    }
}

/**
 * Handles deleting an old file.
 *
 * @param  string|array|null  $pathToDelete  The path of the old file to be deleted.
 * @param  string|null  $disk  The disk to use for storage (default: 'public').
 * @param  string|null  $routeName  The name of the route to use for generating the URL (default: null).
 * @param  string|null  $directory  The directory to store the file (default: '').
 * @return void
 */
if (! function_exists('deleteOldFile')) {
    function deleteOldFile(string|array|null $pathsToDelete, ?string $disk = 'public', ?string $routeName = null): void
    {
        if (empty($pathsToDelete)) {
            return;
        }

        // Coerce the input to an array to handle both strings and arrays uniformly.
        $paths = (array) $pathsToDelete;
        $relativePaths = [];

        // Determine the base URL for conversion once, outside the loop.
        $storageUrl = $routeName ?
            route($routeName, ['path' => '']) :
            config('app.url').Storage::url('');

        // Loop through all paths and convert them to relative paths.
        foreach ($paths as $path) {
            if (empty($path)) {
                continue;
            }

            $relativePath = $path;

            if (Str::startsWith($path, ['http://', 'https://'])) {
                $relativePath = Str::after($path, $storageUrl);
            } elseif (Str::startsWith($path, '/storage')) {
                $relativePath = Str::after($path, '/storage');
            }

            // Add the processed, relative path to our list.
            $relativePaths[] = $relativePath;
        }

        // Perform a single, bulk delete operation if any valid paths were found.
        if (! empty($relativePaths)) {
            Storage::disk($disk)->delete($relativePaths);
        }
    }
}

/**
 * Compares old and new file paths from a model and form data,
 * then deletes any files that are no longer referenced.
 * This is the new "cleanup" helper.
 *
 * @param  Model  $record  The Eloquent model record.
 * @param  array  $dataToUpdate  The newly submitted (and validated) data array.
 * @param  string  $fieldName  The field name (e.g., 'image', 'gallery.*', 'sponsors.*.logo').
 * @param  string  $directory  The directory to store the file. (default: 'images')
 * @param  string  $disk  The storage disk. (default: 'public')
 * @param  string|null  $routeName  The route name for URL generation.
 * @return void
 */
if (! function_exists('syncFileStorage')) {
    function syncFileStorage(
        Model $record,
        array &$dataToUpdate,
        string $fieldName,
        string $directory = 'images',
        string $disk = 'public',
        ?string $routeName = null
    ): void {
        $commitTemporaryFile = function (?string $tempPath) use ($directory, $disk, $fieldName, $routeName) {

            // If the path is null, empty, or not a temp path, do nothing.
            $baseTempUrl = route('temp-file.show', ['path' => '']);
            if (empty($tempPath) || ! Str::startsWith($tempPath, $baseTempUrl)) {
                return $tempPath;
            }

            // Check if the temp file still exists before trying to move it.
            $relativePath = Str::after($tempPath, $baseTempUrl);
            if (! Storage::disk('temp')->exists($relativePath)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $fieldName => 'Your uploaded file has expired. Please upload it again.',
                ]);
            }

            // Create the new permanent path.
            $filename = basename($tempPath);
            $permanentPath = rtrim($directory, '/').'/'.$filename;

            Storage::disk($disk)->writeStream(
                $permanentPath,
                Storage::disk('temp')->readStream($relativePath)
            );

            // Clean up the temp file after moving.
            Storage::disk('temp')->delete($relativePath);

            return $routeName ? route($routeName, ['path' => $permanentPath]) : Storage::disk($disk)->url($permanentPath);
        };

        if (Str::endsWith($fieldName, '.*')) {
            // --- Case 1: Repeater Field (e.g., 'gallery.*') ---
            $baseName = Str::before($fieldName, '.*');
            $oldPaths = collect(data_get($record, $baseName, []));
            $submittedValues = data_get($dataToUpdate, $baseName, []);

            foreach ($submittedValues as &$value) {
                $value = $commitTemporaryFile($value);
            }
            unset($value);

            $pathsToDelete = $oldPaths->filter()->diff($submittedValues);
            data_set($dataToUpdate, $baseName, $submittedValues);

        } elseif (Str::contains($fieldName, '.*.')) {
            // --- Case 2: Repeater Field (e.g., 'sponsors.*.image') ---
            $repeaterName = Str::before($fieldName, '.*.');
            $itemName = Str::after($fieldName, '.*.');
            $oldPaths = collect(data_get($record, $repeaterName, []))->pluck($itemName);
            $newItems = data_get($dataToUpdate, $repeaterName, []);

            foreach ($newItems as &$item) {
                data_set($item, $itemName, $commitTemporaryFile(data_get($item, $itemName)));
            }
            unset($item);

            $finalPaths = collect($newItems)->pluck($itemName);
            $pathsToDelete = $oldPaths->filter()->diff($finalPaths);
            data_set($dataToUpdate, $repeaterName, $newItems);
        } else {
            // --- Case 3: Single File Field (e.g., 'avatar') ---
            $submittedValue = data_get($dataToUpdate, $fieldName);
            $oldPath = data_get($record, $fieldName);
            $pathsToDelete = collect();

            if ($oldPath && $oldPath != $submittedValue) {
                $pathsToDelete->push($oldPath);
            }
            data_set($dataToUpdate, $fieldName, $commitTemporaryFile($submittedValue));
        }

        // --- Cleanup the old files (still needed since files in local and public disk will not be deleted from commitTemporaryFile) ---
        if (isset($pathsToDelete) && $pathsToDelete->isNotEmpty()) {
            deleteOldFile($pathsToDelete->all(), $disk, $routeName);
        }
    }
}

/**
 * Give emoji flag based on country code. Actually only use in resources/views/partials/navbar.blade.php
 * But the testing angry since it recompile each test lmao
 *
 * @param  string  $code  The country code (i.e., 'id', 'fr').
 * @return array The data to be merged for the update.
 */
if (! function_exists('countryFlagEmoji')) {
    function countryFlagEmoji(string $code): string
    {
        $countryCode = strtoupper($code == 'en' ? 'gb' : $code);
        $emoji = '';
        foreach (str_split($countryCode) as $char) {
            $emoji .= mb_convert_encoding('&#'.(127397 + ord($char)).';', 'UTF-8', 'HTML-ENTITIES');
        }

        return $emoji;
    }
}

/**
 * Give all name of admin resources in an array (e.g. users, competitions, faqs)
 *
 * @return array
 */
if (! function_exists('getAdminResources')) {
    function getAdminResources(): array
    {
        $allFiles = File::allFiles(app_path('Http/Controllers/Admin'));

        $classNames = collect($allFiles)
            ->map(function ($file) {
                $class = str_replace(
                    [app_path().'/', '.php'], // Find these parts of the path...
                    ['App/', ''],               // ...and replace them with these.
                    $file->getRealPath()
                );

                // Convert directory separators to namespace separators
                return str_replace('/', '\\', $class);
            })
            ->filter(function ($class) {
                // Ensure the file represents a real, loadable class
                return class_exists($class);
            });

        return $classNames->map(function ($class) {
            $reflection = new ReflectionClass($class);
            $nameValue = null;

            if ($reflection->hasProperty('name') && $reflection->getProperty('name')->isStatic()) {
                $nameValue = $reflection->getStaticPropertyValue('name');
            }

            return $nameValue;
        })->filter()->toArray();
    }
}

/**
 * Finds, authorizes, and streams a private file. The purpose to prevent unauthorized users from accessing private files
 *
 * @param  string  $modelClass  The class name of the model to query (e.g., UserProfile::class).
 * @param  string  $column  The database column where the file URL is stored.
 * @param  string|null  $routeName  The name of the route used to generate the URL.
 * @param  string  $path  The raw file path from the URL.
 * @param  string  $directory  The directory in storage where the file is located.
 * @param  Closure  $permission  A closure for the authorization logic.
 * @param  bool  $isJsonColumn  Whether the column is a JSON column.
 * @param  string  $disk  The storage disk to use (default: 'local').
 * @return StreamedResponse
 */
if (! function_exists('streamPrivateFile')) {
    function streamPrivateFile(
        string $model,
        ?string $routeName,
        string $path,
        string $column,
        Closure $permission,
        bool $isJsonColumn = false,
        ?string $disk = 'local',
    ): StreamedResponse {
        $fileUrl = $routeName ? route($routeName, ['path' => $path]) : $path;

        if ($isJsonColumn) {
            // Use whereJsonContains to find the URL within a JSON array.
            $record = $model::whereJsonContains($column, $fileUrl)->firstOrFail();
        } else {
            // Use the original simple where clause for regular columns.
            $record = $model::where($column, $fileUrl)->firstOrFail();
        }

        if ($permission(Auth::user(), $record)) {
            if (! Storage::disk($disk)->exists($path)) {
                abort(404);
            }

            return Storage::disk($disk)->response($path);
        } else {
            abort(403);
        }
    }
}

/**
 * Dispatches a job to log an admin activity.
 *
 * @param  string  $action  The action being performed (e.g., 'created', 'updated', 'deleted').
 * @param  string  $resourceName  The name of the resource being logged (e.g., 'user', 'competition', 'faq').
 * @param  string|null  $details  Additional details about the activity.
 * @param  Model|null  $record  The model record associated with the activity (optional).
 * @return void
 */
if (! function_exists('admin_log')) {
    function admin_log(string $action, string $resourceName, ?string $details = null, ?Model $record = null): void
    {
        $userID = Auth::id();
        if (empty($userID)) {
            return;
        }

        if ($action === 'edit') {
            $changes = $record->getChanges();
            if (! empty($changes)) {
                unset($changes['updated_at']);

                $logDetails = [];
                foreach ($changes as $field => $newValue) {
                    $oldValue = json_encode($record->getOriginal($field));
                    $newValue = json_encode($newValue);
                    $logDetails[] = "Updated '{$field}' from '{$oldValue}' to '{$newValue}'";
                }

                $details = implode(', ', $logDetails);
            }
        }

        LogAdminActivity::dispatch(
            $userID,
            $action,
            $resourceName,
            $details
        );
    }
}

/**
 * Determines the overall registration status when there can be multiple phases.
 *
 * @param  array  $timeline  An array of timeline events.
 * @return string Returns 'not-started', 'open', or 'closed'.
 */
if (! function_exists('getRegistrationStatus')) {
    function getRegistrationStatus(array $timeline): string
    {
        // Filter to get an array of only the registration phases.
        $registrationPhases = array_filter($timeline, function ($item) {
            return isset($item['is_registration']) && $item['is_registration'] === true;
        });

        // If there are no registration phases at all, it's closed.
        if (empty($registrationPhases)) {
            return 'closed';
        }

        // Sort the phases by their start date. This makes logic much simpler.
        usort($registrationPhases, function ($a, $b) {
            // The spaceship operator (<=>) is perfect for this comparison.
            return Carbon::parse($a['start']) <=> Carbon::parse($b['start']);
        });

        $today = Carbon::now();
        $hasFuturePhase = false;

        // Check if ANY phase is currently open (highest priority).
        foreach ($registrationPhases as $phase) {
            // Ensure data is not malformed before parsing
            if (! isset($phase['start']) || ! isset($phase['end'])) {
                continue;
            }

            $start = Carbon::parse($phase['start']);
            $end = Carbon::parse($phase['end']);

            // isBetween() is inclusive by default when checking dates
            if ($today->isBetween($start, $end)) {
                return 'open';
            }

            if ($start->isFuture()) {
                $hasFuturePhase = true;
            }
        }

        // If the loop finished, nothing is open. Now check if any future phases were found.
        if ($hasFuturePhase) {
            return 'not-started';
        }

        // If nothing is open and nothing is in the future, everything must be closed.
        return 'closed';
    }
}
