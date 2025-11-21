<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwiftEngine Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --apple-gray: #f5f5f7;
            --primary-blue: #0071e3;
        }

        body {
            background-color: var(--apple-gray);
            font-family: 'Inter', sans-serif;
            color: #1d1d1f;
            padding-bottom: 40px;
        }

        .glass-nav {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Layer 1: Date Accordion */
        .accordion-item {
            border: none;
            background: transparent;
            margin-bottom: 16px;
        }

        .accordion-button {
            background-color: white !important;
            border-radius: 18px !important;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
            font-weight: 600;
            color: #1d1d1f;
            padding: 20px 24px;
        }

        .accordion-button:not(.collapsed) {
            color: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(0, 113, 227, 0.1);
            border-bottom-left-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
        }

        .accordion-button:focus {
            box-shadow: none;
        }

        .accordion-body {
            background: white;
            border-bottom-left-radius: 18px;
            border-bottom-right-radius: 18px;
            padding: 0;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
        }

        /* Layer 2: Message Type Header */
        .type-header {
            padding: 15px 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .type-header:hover {
            background-color: #fafafa;
        }

        .type-header:last-child {
            border-bottom: none;
        }

        .type-badge {
            background: #eef2ff;
            color: var(--primary-blue);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 10px;
        }

        /* Blue Liquid Glass Button */
        .btn-liquid-glass {
            background: linear-gradient(135deg, rgba(0, 113, 227, 0.1), rgba(0, 199, 255, 0.1));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 113, 227, 0.2);
            color: var(--primary-blue);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            margin-right: 12px;
        }

        .btn-liquid-glass:hover {
            background: linear-gradient(135deg, var(--primary-blue), #00c7ff);
            color: white;
            box-shadow: 0 4px 10px rgba(0, 113, 227, 0.3);
            border-color: transparent;
            transform: translateY(-1px);
        }

        .btn-liquid-glass svg {
            width: 14px;
            height: 14px;
            stroke-width: 2.5;
        }

        /* Layer 3: Table */
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
            background-color: #fff;
        }

        .ios-table {
            width: 100%;
            font-size: 13px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .ios-table th {
            background: #fafafa;
            color: #86868b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e5e5;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
        }

        .ios-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f5f5f7;
            white-space: nowrap;
            color: #333;
            vertical-align: middle;
        }

        .ios-table tr:last-child td {
            border-bottom: none;
        }

        /* Icons */
        .icon-chevron {
            transition: transform 0.3s;
            width: 16px;
            height: 16px;
            opacity: 0.5;
        }

        .type-header[aria-expanded="true"] .icon-chevron {
            transform: rotate(180deg);
        }

        .truncate-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body>

    <nav class="navbar glass-nav mb-5">
        <div class="container">
            <span class="navbar-brand fw-bold">SwiftEngine</span>
            <span class="text-muted small">Parsed Messages Dashboard</span>
        </div>
    </nav>

    <div class="container">
        @if ($groupedMessages->isEmpty())
            <div class="text-center py-5 text-muted">
                <h4>No messages found</h4>
                <p>Run <code>php artisan swift:process-inbound</code> to load data.</p>
            </div>
        @endif

        <div class="accordion" id="mainAccordion">
            {{-- LAYER 1: DATE --}}
            @foreach ($groupedMessages as $date => $types)
                @php
                    $dateSlug = \Illuminate\Support\Str::slug($date);
                    $dateId = 'date-' . $dateSlug;
                    try {
                        $displayDate = \Carbon\Carbon::parse($date)->toFormattedDateString();
                    } catch (\Exception $e) {
                        $displayDate = $date;
                    }
                    $totalCount = $types->flatten(1)->count();
                @endphp

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#{{ $dateId }}">
                            <span class="me-2 text-muted fw-normal">Processed:</span>
                            <span class="fw-bold">{{ $displayDate }}</span>
                            <span class="ms-auto badge bg-light text-dark border rounded-pill">{{ $totalCount }}
                                msgs</span>
                        </button>
                    </h2>
                    <div id="{{ $dateId }}" class="accordion-collapse collapse" data-bs-parent="#mainAccordion">
                        <div class="accordion-body">

                            {{-- LAYER 2: MESSAGE TYPES --}}
                            @foreach ($types as $type => $messages)
                                @php
                                    $typeId = 'type-' . $dateSlug . '-' . \Illuminate\Support\Str::slug($type);
                                    $shortType = strtok($type, ' ');

                                    // Dynamic Column Logic
                                    $excludeKeys = ['_id', 'updated_at', 'created_at'];
                                    $columns = $messages
                                        ->flatMap(function ($msg) {
                                            return array_keys(is_array($msg) ? $msg : $msg->toArray());
                                        })
                                        ->unique()
                                        ->filter(function ($key) use ($excludeKeys) {
                                            return !in_array($key, $excludeKeys);
                                        })
                                        ->values();
                                @endphp

                                <div class="type-header collapsed" data-bs-toggle="collapse"
                                    data-bs-target="#{{ $typeId }}" aria-expanded="false">
                                    <div class="d-flex align-items-center">
                                        <span class="type-badge">{{ $shortType }}</span>
                                        <span class="fw-medium">{{ $type }}</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <a href="{{ route('download.csv', ['date' => $date, 'type' => $type]) }}"
                                            class="btn-liquid-glass" onclick="event.stopPropagation();"
                                            title="Download CSV">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M12 12.75l-3-3m0 0l-3 3m3-3v12" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v9" />
                                            </svg>
                                            <span>Download</span>
                                        </a>

                                        <span class="text-muted small me-3">{{ count($messages) }} items</span>

                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon-chevron" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>

                                <div id="{{ $typeId }}" class="collapse">
                                    <div class="table-responsive">
                                        <table class="ios-table">
                                            <thead>
                                                <tr>
                                                    @foreach ($columns as $col)
                                                        <th>{{ $col }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($messages as $msg)
                                                    <tr>
                                                        @foreach ($columns as $col)
                                                            <td>
                                                                {{ $msg[$col] ?? '-' }}
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                            {{-- END LAYER 2 --}}

                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mainAccordion = document.getElementById('mainAccordion');

            // Automatically collapse children when parent collapses
            mainAccordion.addEventListener('hide.bs.collapse', function(event) {
                if (event.target.id && event.target.id.startsWith('date-')) {
                    const openChildren = event.target.querySelectorAll('.collapse.show');
                    openChildren.forEach(function(child) {
                        const bsCollapse = bootstrap.Collapse.getInstance(child) || new bootstrap
                            .Collapse(child, {
                                toggle: false
                            });
                        bsCollapse.hide();
                    });
                }
            });
        });
    </script>
</body>

</html>
