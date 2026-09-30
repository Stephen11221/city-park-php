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
    return [
        'parks' => ['title' => 'Parks', 'singular' => 'park', 'description' => 'Manage park locations, opening hours, and availability.', 'columns' => ['park_name', 'location', 'opening_time', 'closing_time', 'status'], 'fields' => [
            'park_name' => $field('Park name', 'text', true, ['max' => 150]),
            'image_path' => $field('Photo path', 'image', false, ['max' => 255]),
            'location' => $field('Location', 'text', true, ['max' => 255]),
            'description' => $field('Description', 'textarea', false),
            'opening_time' => $field('Opening time', 'time', false),
            'closing_time' => $field('Closing time', 'time', false),
            'status' => $select('Status', ['open', 'closed', 'maintenance'], 'open'),
        ]],
        'facilities' => ['title' => 'Facilities', 'singular' => 'facility', 'description' => 'Spaces and amenities available within each park.', 'columns' => ['facility_name', 'park_id', 'capacity', 'price', 'status'], 'fields' => [
            'park_id' => $ref('Park', 'parks', 'park_name'),
            'facility_name' => $field('Facility name', 'text', true, ['max' => 150]),
            'image_path' => $field('Photo path', 'image', false, ['max' => 255]),
            'description' => $field('Description', 'textarea', false),
            'capacity' => $field('Capacity', 'integer', true, ['min' => 0, 'default' => '0']),
            'price' => $field('Price per booking', 'money', true, ['default' => '0.00']),
            'status' => $select('Status', ['available', 'unavailable', 'maintenance'], 'available'),
        ]],
        'bookings' => ['title' => 'Bookings', 'singular' => 'booking', 'description' => 'Schedule facility visits and manage booking decisions.', 'columns' => ['user_id', 'facility_id', 'booking_date', 'start_time', 'end_time', 'total_amount', 'status'], 'fields' => [
            'user_id' => $ref('Customer', 'users', 'full_name'),
            'facility_id' => $ref('Facility', 'facilities', 'facility_name'),
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
            'role' => $select('Role', ['admin', 'staff', 'customer'], 'customer'),
            'status' => $select('Status', ['active', 'inactive'], 'active'),
        ]],
        'activity_logs' => ['title' => 'Activity logs', 'singular' => 'activity', 'description' => 'Read-only history of sign-ins and changes made through this app.', 'columns' => ['user_id', 'action', 'ip_address', 'created_at'], 'fields' => [
            'user_id' => $ref('User', 'users', 'full_name', false),
            'action' => $field('Action'),
            'ip_address' => $field('IP address', 'text', false),
            'created_at' => $field('Created at'),
        ]],
    ];
}
