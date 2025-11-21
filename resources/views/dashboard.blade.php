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

        /* Layer 1: Date */
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

        /* Layer 2: Message Type */
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

        .type-badge {
            background: #eef2ff;
            color: var(--primary-blue);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 10px;
        }

        /* Layer 3: Table */
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
        }

        /* Icons */
        .icon-chevron {
            transition: transform 0.3s;
        }

        .collapsed .icon-chevron {
            transform: rotate(0deg);
        }

        .type-header:not(.collapsed) .icon-chevron {
            transform: rotate(180deg);
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
                    $dateId = 'date-' . \Illuminate\Support\Str::slug($date);
                    $displayDate = \Carbon\Carbon::parse($date)->toFormattedDateString();
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


                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
