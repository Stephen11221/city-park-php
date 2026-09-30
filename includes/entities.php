<?php
declare(strict_types=1);
function entities(): array
{
    $field = static function (string $label, string $type = 'text', bool $required = true, array $extra = []): array {
        return array_merge(compact('label', 'type', 'required'), $extra);
    };
    $select = static function (string $label, array $options, string $default) use ($field): array {
        return $field($label, 'select', true, compact('options', 'default'));
    };
    $ref = static function (string $label, string $table, string $column, bool $required = true) use ($field): array {
        return $field($label, 'reference', $required, ['table' => $table, 'column' => $column]);
    };
    $definitions = [
        'parks' => ['title' => 'Parks', 'singular' => 'park', 'description' => 'Manage park locations, opening hours, and availability.', 'columns' => ['park_name', 'location', 'opening_time', 'closing_time', 'status'], 'fields' => [
            'park_name' => $field('Park name', 'text', true, ['max' => 150]),
            'image_path' => $field('Photo', 'image', false, ['max' => 255]),
            'location' => $field('Location', 'text', true, ['max' => 255]),
            'description' => $field('Description', 'textarea', false),
            'opening_time' => $field('Opening time', 'time', false),
            'closing_time' => $field('Closing time', 'time', false),
            'status' => $select('Status', ['open', 'closed', 'maintenance'], 'open'),
        ]],
        'facilities' => ['title' => 'Facilities', 'singular' => 'facility', 'description' => 'Spaces and amenities available within each park.', 'columns' => ['facility_name', 'park_id', 'capacity', 'price', 'status'], 'fields' => [
            'park_id' => $ref('Park', 'parks', 'park_name'),
            'facility_name' => $field('Facility name', 'text', true, ['max' => 150]),
            'kind' => $select('Type', ['facility', 'table'], 'facility'),
            'image_path' => $field('Photo', 'image', false, ['max' => 255]),
            'description' => $field('Description', 'textarea', false),
            'capacity' => $field('Capacity', 'integer', true, ['min' => 0, 'default' => '0']),
            'price' => $field('Price per booking', 'money', true, ['default' => '0.00']),
            'status' => $select('Status', ['available', 'unavailable', 'maintenance'], 'available'),
        ]],
        'bookings' => ['title' => 'Bookings', 'singular' => 'booking', 'description' => 'Schedule facility visits and manage booking decisions.', 'columns' => ['user_id', 'facility_id', 'booking_date', 'start_time', 'end_time', 'total_amount', 'status'], 'fields' => [
            'user_id' => $ref('Customer', 'users', 'full_name'),
            'facility_id' => $ref('Facility / table', 'facilities', 'facility_name'),
            'booking_date' => $field('Booking date', 'date'),
            'start_time' => $field('Start time', 'time'),
            'end_time' => $field('End time', 'time'),
            'number_of_people' => $field('Number of people', 'integer', true, ['min' => 1, 'default' => '1']),
            'total_amount' => $field('Total amount', 'money', true, ['default' => '0.00']),
            'status' => $select('Status', ['pending', 'approved', 'rejected', 'cancelled', 'completed'], 'pending'),
        ]],
        'payments' => ['title' => 'Payments', 'singular' => 'payment', 'description' => 'Record and review payments. These records do not charge a card or initiate an M-Pesa transfer.', 'columns' => ['booking_id', 'transaction_reference', 'amount', 'payment_method', 'payment_status', 'paid_at'], 'fields' => [
            'booking_id' => $ref('Booking', 'bookings', 'id'),
            'transaction_reference' => $field('Transaction reference', 'text', false, ['max' => 100]),
            'amount' => $field('Amount', 'money'),
            'payment_method' => $select('Payment method', ['cash', 'mpesa', 'card', 'bank'], 'mpesa'),
            'payment_status' => $select('Payment status', ['pending', 'paid', 'failed', 'refunded'], 'pending'),
            'paid_at' => $field('Paid at', 'datetime-local', false),
        ]],
        'maintenance' => ['title' => 'Maintenance', 'singular' => 'maintenance report', 'description' => 'Track facility issues, staff assignments, and repairs.', 'columns' => ['title', 'facility_id', 'assigned_to', 'priority', 'status', 'reported_date'], 'fields' => [
            'facility_id' => $ref('Facility', 'facilities', 'facility_name'),
            'title' => $field('Title', 'text', true, ['max' => 150]),
            'description' => $field('Description', 'textarea', false),
            'reported_by' => $ref('Reported by', 'users', 'full_name', false),
            'assigned_to' => $ref('Assigned staff member', 'users', 'full_name', false),
            'priority' => $select('Priority', ['low', 'medium', 'high', 'urgent'], 'medium'),
            'status' => $select('Status', ['reported', 'in_progress', 'completed'], 'reported'),
            'reported_date' => $field('Reported date', 'date', true, ['default' => date('Y-m-d')]),
            'completed_date' => $field('Completed date', 'date', false),
        ]],
        'users' => ['title' => 'Users', 'singular' => 'user', 'description' => 'Manage accounts, roles, and account access.', 'columns' => ['full_name', 'email', 'phone', 'role', 'status'], 'fields' => [
            'full_name' => $field('Full name', 'text', true, ['max' => 100]),
            'email' => $field('Email', 'email', true, ['max' => 150]),
            'phone' => $field('Phone', 'text', false, ['max' => 20]),
            'password' => $field('Password', 'password'),
            'role' => $select('Role', ['admin', 'staff', 'customer', 'cashier'], 'customer'),
            'status' => $select('Status', ['active', 'inactive'], 'active'),
        ]],
        'activity_logs' => ['title' => 'Activity logs', 'singular' => 'activity', 'description' => 'Read-only history of sign-ins and changes made through this app.', 'columns' => ['user_id', 'action', 'ip_address', 'created_at'], 'fields' => [
            'user_id' => $ref('User', 'users', 'full_name', false),
            'action' => $field('Action'),
            'ip_address' => $field('IP address', 'text', false),
            'created_at' => $field('Created at'),
        ]],
    ];
    $definitions['menu_items'] = ['title' => 'Menu items', 'singular' => 'menu item', 'description' => 'Manage food and drinks shown on the public menu.', 'columns' => ['item_name', 'category', 'price', 'status'], 'fields' => [
        'item_name' => $field('Item name', 'text', true, ['max' => 150]),
        'category' => $select('Category', ['meals', 'snacks', 'drinks', 'desserts'], 'meals'),
        'description' => $field('Description', 'textarea', false),
        'price' => $field('Price', 'money'),
        'image_path' => $field('Photo', 'image', false, ['max' => 255]),
        'status' => $select('Status', ['available', 'unavailable'], 'available'),
    ]];
    $definitions['staff'] = $definitions['users'];
    $definitions['staff']['table'] = 'users';
    $definitions['staff']['title'] = 'Staff';
    $definitions['staff']['singular'] = 'staff member';
    $definitions['staff']['description'] = 'Hire staff, set daily and monthly pay rates, and manage employment and account access.';
    $definitions['staff']['fixed'] = ['role' => 'staff'];
    unset($definitions['staff']['fields']['role']);
    $definitions['staff']['columns'] = ['full_name', 'job_title', 'hired_on', 'employment_status', 'daily_rate', 'monthly_rate', 'status'];
    $definitions['staff']['fields']['job_title'] = $field('Job title', 'text', false, ['max' => 100]);
    $definitions['staff']['fields']['hired_on'] = $field('Hire date', 'date', true, ['default' => date('Y-m-d')]);
    $definitions['staff']['fields']['daily_rate'] = $field('Daily pay rate', 'money', true, ['default' => '0.00']);
    $definitions['staff']['fields']['monthly_rate'] = $field('Monthly pay rate', 'money', true, ['default' => '0.00']);
    $definitions['staff']['fields']['employment_status'] = $field('Employment', 'text', false, ['readonly' => true]);
    $definitions['staff']['fields']['terminated_on'] = $field('Dismissal date', 'date', false, ['readonly' => true]);
    $definitions['staff']['fields']['termination_reason'] = $field('Dismissal reason', 'textarea', false, ['readonly' => true]);
    $definitions['staff_payments'] = ['title' => 'Staff payments', 'singular' => 'pay allocation', 'description' => 'Allocate one day’s pay or one month’s salary using the staff member’s saved rate. These records do not transfer money.', 'columns' => ['staff_id', 'pay_basis', 'period_start', 'amount', 'status', 'paid_on'], 'fields' => [
        'staff_id' => $ref('Staff member', 'users', 'full_name'),
        'pay_basis' => $select('Pay basis', ['daily', 'monthly'], 'daily'),
        'period_start' => $field('Pay date / first day of month', 'date', true, ['default' => date('Y-m-d')]),
        'amount' => $field('Allocated amount', 'money', false, ['readonly' => true]),
        'status' => $select('Payment status', ['allocated', 'paid', 'cancelled'], 'allocated'),
        'paid_on' => $field('Date paid', 'date', false),
        'reference' => $field('Payment reference', 'text', false, ['max' => 100]),
        'notes' => $field('Notes', 'textarea', false),
    ]];
    $definitions['suppliers'] = ['title' => 'Suppliers', 'singular' => 'supplier', 'description' => 'Manage supplier contacts, goods and services, and availability.', 'columns' => ['supplier_name', 'contact_person', 'phone', 'supplies', 'status'], 'fields' => [
        'supplier_name' => $field('Supplier name', 'text', true, ['max' => 150]),
        'contact_person' => $field('Contact person', 'text', false, ['max' => 100]),
        'email' => $field('Email', 'email', false, ['max' => 150]),
        'phone' => $field('Phone', 'text', false, ['max' => 20]),
        'address' => $field('Address', 'text', false, ['max' => 255]),
        'supplies' => $field('Goods / services supplied', 'text', true, ['max' => 255]),
        'status' => $select('Status', ['active', 'inactive'], 'active'),
        'notes' => $field('Notes', 'textarea', false),
    ]];
    $definitions['dining_tables'] = $definitions['facilities'];
    $definitions['dining_tables']['table'] = 'facilities';
    $definitions['dining_tables']['title'] = 'Tables';
    $definitions['dining_tables']['singular'] = 'table';
    $definitions['dining_tables']['description'] = 'Manage individual tables, seating capacity, reservation fees, and availability.';
    $definitions['dining_tables']['fixed'] = ['kind' => 'table'];
    unset($definitions['dining_tables']['fields']['kind']);
    $definitions['dining_tables']['fields']['facility_name']['label'] = 'Table name / number';
    $definitions['dining_tables']['fields']['capacity']['label'] = 'Seats';
    $definitions['dining_tables']['fields']['capacity']['min'] = 1;
    $definitions['dining_tables']['fields']['capacity']['default'] = '4';
    $definitions['dining_tables']['fields']['price']['label'] = 'Reservation fee';
    $definitions['expenses'] = ['title' => 'Expenses', 'singular' => 'expense', 'description' => 'Record operating expenses and supplier payments for the cash report. Do not enter staff wages here; paid staff allocations are included separately.', 'columns' => ['title', 'supplier_id', 'amount', 'expense_date', 'payment_method'], 'fields' => [
        'title' => $field('Expense description', 'text', true, ['max' => 150]),
        'supplier_id' => $ref('Supplier', 'suppliers', 'supplier_name', false),
        'amount' => $field('Amount paid', 'money'),
        'expense_date' => $field('Date paid', 'date', true, ['default' => date('Y-m-d')]),
        'payment_method' => $select('Payment method', ['cash', 'mpesa', 'card', 'bank'], 'cash'),
        'reference' => $field('Payment reference', 'text', false, ['max' => 100]),
        'notes' => $field('Notes', 'textarea', false),
    ]];
    return $definitions;
}
