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
            --card-radius: 18px;
            --card-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
            --primary-blue: #0071e3;
        }

        body {
            background-color: var(--apple-gray);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #1d1d1f;
            padding-bottom: 40px;
        }

        /* Navbar */
        .glass-nav {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .brand-logo {
            font-weight: 600;
            letter-spacing: -0.5px;
            color: #1d1d1f;
        }

        /* Accordion Styling */
        .accordion-item {
            border: none;
            background: transparent;
            margin-bottom: 16px;
        }

        .accordion-button {
            background-color: white !important;
            border-radius: var(--card-radius) !important;
            box-shadow: var(--card-shadow);
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
            border-color: rgba(0, 0, 0, 0.1);
        }

        .accordion-body {
            background: white;
            border-bottom-left-radius: var(--card-radius);
            border-bottom-right-radius: var(--card-radius);
            padding: 0;
            box-shadow: var(--card-shadow);
        }

        /* Message Type List */
        .type-header {
            padding: 15px 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            /* Pushes content to Left and Right edges */
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .type-header:hover {
            background-color: #fafafa;
        }

        .type-header.collapsed .icon-chevron {
            transform: rotate(0deg);
        }

        .type-header .icon-chevron {
            transform: rotate(180deg);
            transition: transform 0.3s;
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

        /* Table */
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }

        .ios-table {
            width: 100%;
            font-size: 13px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .ios-table thead th {
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

        .ios-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #f5f5f7;
            white-space: nowrap;
        }

        .ios-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Pagination */
        .pagination {
            margin-top: 2rem;
            justify-content: center;
        }

        .page-link {
            color: var(--primary-blue);
            border-radius: 8px;
            margin: 0 2px;
            border: none;
            background: transparent;
        }

        .page-item.active .page-link {
            background-color: var(--primary-blue);
            color: white;
        }

        .page-link:hover {
            background-color: #eef2ff;
        }
    </style>
</head>

<body>

    <nav class="navbar glass-nav mb-5">
        <div class="container">
            <a class="navbar-brand brand-logo" href="#">
                SwiftEngine
            </a>
            <span class="text-muted small">Parsed Messages Dashboard</span>
        </div>
    </nav>

    <div class="container">
        @if ($groupedMessages->isEmpty())
            <div class="text-center py-5 text-muted">
                <h4>No messages found</h4>
                <p>Run <code>php artisan swift:process-inbound</code> to process files.</p>
            </div>
        @endif

        {{-- Level 1: Date Accordion --}}
        <div class="accordion" id="dateAccordion">
            @foreach ($groupedMessages as $date => $types)
                @php
                    $dateId = \Illuminate\Support\Str::slug($date);
                    $totalMessages = $types->flatten(1)->count();
                    $prettyDate = \Carbon\Carbon::parse($date)->toFormattedDateString();
                @endphp

                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-{{ $dateId }}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapse-{{ $dateId }}" aria-expanded="false">
                            <span class="me-2 text-muted fw-normal">Processed:</span>
                            <span class="fw-semibold">{{ $prettyDate }}</span>
                            <span class="ms-auto badge bg-light text-dark border rounded-pill">
                                {{ $totalMessages }} msgs
                            </span>
                        </button>
                    </h2>
                    <div id="collapse-{{ $dateId }}" class="accordion-collapse collapse"
                        data-bs-parent="#dateAccordion">
                        <div class="accordion-body">

                            {{-- Level 2: Message Type List --}}
                            @foreach ($types as $type => $messages)
                                @php
                                    $typeId = $dateId . '-' . $type;
                                    $meaning = \App\Services\SwiftCodeTranslator::translateMessageType($type);
                                    $typeCount = count($messages); // Count specific to this type on this day

                                    // DYNAMIC HEADERS logic
                                    $firstMsg = $messages->first();
                                    $headers = [];
                                    if ($firstMsg) {
                                        $allAttributes = $firstMsg->getAttributes();
                                        $headers = array_filter(array_keys($allAttributes), function ($key) {
                                            return !str_starts_with($key, '_') &&
                                                !in_array($key, ['updated_at', 'created_at']);
                                        });
                                    }
                                @endphp

                                <div class="message-group">
                                    {{-- Enhanced Header Design: Type Left, Count Right --}}
                                    <div class="type-header collapsed" data-bs-toggle="collapse"
                                        data-bs-target="#table-{{ $typeId }}">

                                        {{-- Left Side: MT Code + Meaning --}}
                                        <div class="d-flex align-items-center">
                                            <span class="type-badge">MT {{ $type }}</span>
                                            <span class="fw-medium text-dark">({{ $meaning }})</span>
                                        </div>

                                        {{-- Right Side: Count + Chevron --}}
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="badge bg-light text-secondary border rounded-pill px-3">
                                                {{ $typeCount }} messages
                                            </span>
                                            <svg class="icon-chevron" width="12" height="7" viewBox="0 0 12 7"
                                                fill="none" stroke="#86868b" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M1 1L6 6L11 1" />
                                            </svg>
                                        </div>
                                    </div>

                                    {{-- Level 3: Consolidated Table --}}
                                    <div id="table-{{ $typeId }}" class="collapse">
                                        <div class="table-responsive">
                                            <table class="ios-table">
                                                <thead>
                                                    <tr>
                                                        @foreach ($headers as $header)
                                                            <th>{{ $header }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($messages as $msg)
                                                        <tr>
                                                            @foreach ($headers as $key)
                                                                @php
                                                                    $val = $msg->attributes[$key] ?? '';
                                                                    if (is_array($val) || is_object($val)) {
                                                                        $val = json_encode($val);
                                                                    }
                                                                @endphp
                                                                <td title="{{ $val }}">
                                                                    {{ \Illuminate\Support\Str::limit($val, 50) }}
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination Links --}}
        <div class="d-flex justify-content-center">
            {{ $paginator->links('pagination::bootstrap-5') }}
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
