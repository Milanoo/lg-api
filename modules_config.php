<?php
// ============================================================
// Central registry of Local-Government API pages.
//
// Every page in this project (staff directory today, more to
// come) includes this file to render the same "Modules" switcher
// in its header. Add an entry here once a module's API is wired
// up; set 'file' once its page exists - until then it shows as
// "Soon" and is not clickable.
// ============================================================

return [
    ['key' => 'elected-officials-api', 'label' => 'Elected Officials',  'label_np' => 'निर्वाचित पदाधिकारी',   'file' => 'elected_officials_report.php', 'desc' => 'Elected representatives of the municipality', 'fetch_endpoint' => 'fetch_elected_officials.php', 'cache_file' => 'elected_officials_data.json', 'kind' => 'person_directory'],    
    ['key' => 'staff-api',             'label' => 'Staff Directory',    'label_np' => 'कर्मचारी विवरण',       'file' => 'lg_staff_report.php', 'desc' => 'Staff of all Local Governments', 'fetch_endpoint' => 'fetch_staff.php', 'cache_file' => 'staff_data.json', 'kind' => 'person_directory'],
    ['key' => 'article-api',           'label' => 'News & Notices',     'label_np' => 'समाचार तथा सूचना',     'file' => null, 'desc' => 'News, notices and announcements'],
    ['key' => 'documents-api',         'label' => 'Documents',          'label_np' => 'कागजातहरू',            'file' => null, 'desc' => 'Uploaded documents'],
    ['key' => 'wards-api',             'label' => 'Wards',              'label_np' => 'वडाहरू',               'file' => null, 'desc' => 'Ward information'],
    ['key' => 'gallery-api',           'label' => 'Gallery',            'label_np' => 'ग्यालरी',              'file' => null, 'desc' => 'Photos from the gallery section'],
    ['key' => 'resource-map-api',      'label' => 'Resource Maps',      'label_np' => 'स्रोत नक्सा',          'file' => null, 'desc' => 'Uploaded resource maps'],
    ['key' => 'services-api',          'label' => 'Services',           'label_np' => 'सेवाहरू',              'file' => null, 'desc' => 'Service listings'],
    ['key' => 'emergency-number-api',  'label' => 'Emergency Contacts', 'label_np' => 'आपतकालीन सम्पर्क',      'file' => null, 'desc' => 'Emergency contact numbers'],
    ['key' => 'contact-api',           'label' => 'Contact',            'label_np' => 'सम्पर्क',              'file' => null, 'desc' => 'Municipality office contact details'],
    ['key' => 'ward-officials-api',    'label' => 'Ward Officials',     'label_np' => 'वडा जनप्रतिनिधि',       'file' => null, 'desc' => 'Elected ward representatives'],
    ['key' => 'elected-profile-api',   'label' => 'Elected Profile',    'label_np' => 'जनप्रतिनिधि प्रोफाइल',  'file' => null, 'desc' => 'Elected representative profile'],
    ['key' => 'important-places-api',  'label' => 'Important Places',   'label_np' => 'महत्वपूर्ण स्थान',      'file' => null, 'desc' => 'Places with latitude / longitude'],
    ['key' => 'slider-api',            'label' => 'Homepage Sliders',   'label_np' => 'स्लाइडर',              'file' => null, 'desc' => 'Homepage slider images'],

    // Not one of the 13 content APIs - an internal debugging/ops utility that
    // probes each LG's website directly rather than calling an API on it.
    ['key' => 'website-status',        'label' => 'Website Status',     'label_np' => 'वेबसाइट स्थिति',       'file' => 'website_directory.php', 'desc' => 'Live up/down status, response time and server IP for every LG website', 'fetch_endpoint' => 'fetch_website_status.php', 'cache_file' => 'website_status_data.json', 'kind' => 'website_status'],
];
