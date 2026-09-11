<?php

return [
    'brand' => 'Royadarman workspace',
    'back_home' => 'Public site',
    'logout' => 'Sign out',
    'marketing' => 'Marketing CMS',
    'roles' => [
        'patient' => ['title' => 'My care requests', 'subtitle' => 'Your own requests, documents and progress only.'],
        'coordinator' => ['title' => 'Coordination workspace', 'subtitle' => 'Operational cases explicitly assigned to you.'],
        'clinician' => ['title' => 'Clinical review workspace', 'subtitle' => 'Only cases and review material explicitly assigned to you.'],
        'clinic_rep' => ['title' => 'Partner clinic workspace', 'subtitle' => 'Only referrals covered by an active patient grant.'],
        'owner' => ['title' => 'Owner operations overview', 'subtitle' => 'Aggregate operations and network administration; no routine patient or clinical browsing.'],
        'tech_admin' => ['title' => 'Technical operations', 'subtitle' => 'System health and queues only; no routine clinical browsing.'],
    ],
    'metrics' => [
        'cases' => 'Cases', 'active' => 'Active', 'documents' => 'Documents', 'assigned' => 'Assigned',
        'awaiting' => 'Awaiting patient', 'urgent' => 'Urgent', 'draft_reviews' => 'Draft reviews',
        'published_reviews' => 'Published reviews', 'active_referrals' => 'Active referrals',
        'expiring_soon' => 'Expiring soon', 'open_cases' => 'Open cases', 'active_clinics' => 'Active clinics',
        'verified_clinicians' => 'Verified clinicians', 'notification_failures' => 'Notification failures',
        'queued_jobs' => 'Queued jobs', 'failed_jobs' => 'Failed jobs', 'pending_outbox' => 'Pending outbox',
        'scan_failures' => 'Scan failures', 'pending_retention' => 'Pending retention',
    ],
    'table' => ['reference' => 'Reference', 'service' => 'Service', 'status' => 'Status', 'updated' => 'Updated'],
    'empty' => 'Nothing currently requires your attention.',
];
