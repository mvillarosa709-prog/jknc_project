-- MySQL/MariaDB: LOCAL PREPARATION ONLY. Never executed by this kit.
-- Run with a read-only account. No application records or tokens are selected.
-- Stage 1: metadata. table_rows is approximate for InnoDB.
SELECT VERSION() AS server_version;
SELECT DATABASE() AS selected_database;
SELECT @@sql_mode AS sql_mode, @@character_set_database AS database_charset, @@collation_database AS database_collation;
SELECT table_name, engine, table_rows AS approximate_rows, data_length, index_length,
       ROUND((data_length + index_length) / 1024 / 1024, 2) AS total_mb, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name IN ('activity_attachments',
    'activity_audit_logs',
    'activity_links',
    'branches',
    'cache',
    'cache_locks',
    'calls',
    'companies',
    'company_activities',
    'company_travel_credit_accounts',
    'company_travel_credit_entries',
    'contacts',
    'deals',
    'departments',
    'email_activities',
    'email_mailbox_connections',
    'employees',
    'events',
    'failed_jobs',
    'finance_records',
    'human_capital_logs',
    'job_batches',
    'jobs',
    'meeting_action_items',
    'meeting_minutes_versions',
    'meetings',
    'message_conversations',
    'message_entries',
    'message_entry_attachments',
    'messaging_provider_peers',
    'messaging_provider_receipts',
    'messaging_webhook_events',
    'messenger_outbound_attempts',
    'migrations',
    'notes',
    'notifications',
    'official_business_trip_personnel',
    'official_business_trip_ticket_costs',
    'official_business_trip_ticket_financial_treatments',
    'official_business_trip_ticket_personnel',
    'official_business_trip_ticket_stops',
    'official_business_trip_tickets',
    'official_business_trips',
    'password_reset_tokens',
    'projects',
    'role_permissions',
    'sessions',
    'task_assignments',
    'task_dependencies',
    'task_templates',
    'task_timers',
    'tasks',
    'user_permissions',
    'users',
    'visit_movements',
    'visitors',
    'visits') ORDER BY table_name;
SELECT table_name, column_name, column_type, is_nullable, column_default, character_set_name, collation_name, extra
FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name IN ('activity_attachments',
    'activity_audit_logs',
    'activity_links',
    'branches',
    'cache',
    'cache_locks',
    'calls',
    'companies',
    'company_activities',
    'company_travel_credit_accounts',
    'company_travel_credit_entries',
    'contacts',
    'deals',
    'departments',
    'email_activities',
    'email_mailbox_connections',
    'employees',
    'events',
    'failed_jobs',
    'finance_records',
    'human_capital_logs',
    'job_batches',
    'jobs',
    'meeting_action_items',
    'meeting_minutes_versions',
    'meetings',
    'message_conversations',
    'message_entries',
    'message_entry_attachments',
    'messaging_provider_peers',
    'messaging_provider_receipts',
    'messaging_webhook_events',
    'messenger_outbound_attempts',
    'migrations',
    'notes',
    'notifications',
    'official_business_trip_personnel',
    'official_business_trip_ticket_costs',
    'official_business_trip_ticket_financial_treatments',
    'official_business_trip_ticket_personnel',
    'official_business_trip_ticket_stops',
    'official_business_trip_tickets',
    'official_business_trips',
    'password_reset_tokens',
    'projects',
    'role_permissions',
    'sessions',
    'task_assignments',
    'task_dependencies',
    'task_templates',
    'task_timers',
    'tasks',
    'user_permissions',
    'users',
    'visit_movements',
    'visitors',
    'visits')
ORDER BY table_name, ordinal_position;
SELECT table_name, index_name, non_unique, seq_in_index, column_name, sub_part, index_type
FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name IN ('activity_attachments',
    'activity_audit_logs',
    'activity_links',
    'branches',
    'cache',
    'cache_locks',
    'calls',
    'companies',
    'company_activities',
    'company_travel_credit_accounts',
    'company_travel_credit_entries',
    'contacts',
    'deals',
    'departments',
    'email_activities',
    'email_mailbox_connections',
    'employees',
    'events',
    'failed_jobs',
    'finance_records',
    'human_capital_logs',
    'job_batches',
    'jobs',
    'meeting_action_items',
    'meeting_minutes_versions',
    'meetings',
    'message_conversations',
    'message_entries',
    'message_entry_attachments',
    'messaging_provider_peers',
    'messaging_provider_receipts',
    'messaging_webhook_events',
    'messenger_outbound_attempts',
    'migrations',
    'notes',
    'notifications',
    'official_business_trip_personnel',
    'official_business_trip_ticket_costs',
    'official_business_trip_ticket_financial_treatments',
    'official_business_trip_ticket_personnel',
    'official_business_trip_ticket_stops',
    'official_business_trip_tickets',
    'official_business_trips',
    'password_reset_tokens',
    'projects',
    'role_permissions',
    'sessions',
    'task_assignments',
    'task_dependencies',
    'task_templates',
    'task_timers',
    'tasks',
    'user_permissions',
    'users',
    'visit_movements',
    'visitors',
    'visits')
ORDER BY table_name, index_name, seq_in_index;
SELECT k.table_name, k.constraint_name, k.column_name, k.referenced_table_name, k.referenced_column_name,
       r.update_rule, r.delete_rule
FROM information_schema.key_column_usage k
LEFT JOIN information_schema.referential_constraints r ON r.constraint_schema = k.constraint_schema AND r.constraint_name = k.constraint_name AND r.table_name = k.table_name
WHERE k.table_schema = DATABASE() AND k.table_name IN ('activity_attachments',
    'activity_audit_logs',
    'activity_links',
    'branches',
    'cache',
    'cache_locks',
    'calls',
    'companies',
    'company_activities',
    'company_travel_credit_accounts',
    'company_travel_credit_entries',
    'contacts',
    'deals',
    'departments',
    'email_activities',
    'email_mailbox_connections',
    'employees',
    'events',
    'failed_jobs',
    'finance_records',
    'human_capital_logs',
    'job_batches',
    'jobs',
    'meeting_action_items',
    'meeting_minutes_versions',
    'meetings',
    'message_conversations',
    'message_entries',
    'message_entry_attachments',
    'messaging_provider_peers',
    'messaging_provider_receipts',
    'messaging_webhook_events',
    'messenger_outbound_attempts',
    'migrations',
    'notes',
    'notifications',
    'official_business_trip_personnel',
    'official_business_trip_ticket_costs',
    'official_business_trip_ticket_financial_treatments',
    'official_business_trip_ticket_personnel',
    'official_business_trip_ticket_stops',
    'official_business_trip_tickets',
    'official_business_trips',
    'password_reset_tokens',
    'projects',
    'role_permissions',
    'sessions',
    'task_assignments',
    'task_dependencies',
    'task_templates',
    'task_timers',
    'tasks',
    'user_permissions',
    'users',
    'visit_movements',
    'visitors',
    'visits') AND k.referenced_table_name IS NOT NULL
ORDER BY k.table_name, k.constraint_name, k.ordinal_position;
-- Required Gmail base FK types: users.id and email_activities.mailbox_owner_user_id.
SELECT table_name, column_name, column_type, is_nullable
FROM information_schema.columns WHERE table_schema = DATABASE()
AND ((table_name = 'users' AND column_name = 'id') OR
     (table_name = 'email_activities' AND column_name IN ('id','mailbox_owner_user_id','provider','mailbox_key','provider_message_id','mailbox_connection_id','thread_id','idempotency_key')) OR
     (table_name = 'email_mailbox_connections' AND column_name IN ('id','user_id','provider','email_address','sync_checkpoint','sync_status','initial_sync_status','initial_sync_count')));
SELECT expected.table_name, expected.column_name, c.column_type,
       CASE WHEN c.column_name IS NULL THEN 'STOP: prerequisite missing' ELSE 'PRESENT: review type/charset/nullability' END AS compatibility
FROM (
    SELECT 'users' AS table_name, 'id' AS column_name
    UNION ALL SELECT 'email_activities','id'
    UNION ALL SELECT 'email_activities','mailbox_owner_user_id'
    UNION ALL SELECT 'email_activities','provider'
    UNION ALL SELECT 'email_activities','mailbox_key'
    UNION ALL SELECT 'email_activities','provider_message_id'
) expected LEFT JOIN information_schema.columns c ON c.table_schema=DATABASE() AND c.table_name=expected.table_name AND c.column_name=expected.column_name;
SELECT table_name, column_name, column_type,
       CASE WHEN LOWER(column_type) LIKE 'bigint%unsigned' THEN 'TYPE MATCH: BIGINT UNSIGNED' ELSE 'STOP: verify Gmail FK type mismatch' END AS gmail_fk_compatibility
FROM information_schema.columns WHERE table_schema=DATABASE() AND ((table_name='users' AND column_name='id') OR (table_name='email_activities' AND column_name='mailbox_owner_user_id'));
SELECT table_name, column_name, 'IF MIGRATION UNRECORDED: STOP for partial-schema reconciliation' AS partial_migration_check
FROM information_schema.columns WHERE table_schema=DATABASE() AND
((table_name='email_mailbox_connections') OR (table_name='email_activities' AND column_name IN ('mailbox_connection_id','thread_id','rfc_message_id','in_reply_to','references_header','bcc_recipients','delivery_status','sent_at','provider_error','idempotency_key')));
-- Non-idempotent Gmail migrations: any Gmail target fields already present while their migration
-- is unrecorded require reconciliation, not a rerun. All users FK IDs must match BIGINT UNSIGNED.
-- Stage 2: generated SELECTs. Copy and run the returned sql_to_run statements using the same
-- read-only session. Generation gates missing tables/columns; no PREPARE/EXECUTE/DDL is used.
SELECT CONCAT('SELECT COUNT(*) AS exact_rows FROM ', CHAR(96), table_name, CHAR(96), ';') AS sql_to_run
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('activity_attachments',
    'activity_audit_logs',
    'activity_links',
    'branches',
    'cache',
    'cache_locks',
    'calls',
    'companies',
    'company_activities',
    'company_travel_credit_accounts',
    'company_travel_credit_entries',
    'contacts',
    'deals',
    'departments',
    'email_activities',
    'email_mailbox_connections',
    'employees',
    'events',
    'failed_jobs',
    'finance_records',
    'human_capital_logs',
    'job_batches',
    'jobs',
    'meeting_action_items',
    'meeting_minutes_versions',
    'meetings',
    'message_conversations',
    'message_entries',
    'message_entry_attachments',
    'messaging_provider_peers',
    'messaging_provider_receipts',
    'messaging_webhook_events',
    'messenger_outbound_attempts',
    'migrations',
    'notes',
    'notifications',
    'official_business_trip_personnel',
    'official_business_trip_ticket_costs',
    'official_business_trip_ticket_financial_treatments',
    'official_business_trip_ticket_personnel',
    'official_business_trip_ticket_stops',
    'official_business_trip_tickets',
    'official_business_trips',
    'password_reset_tokens',
    'projects',
    'role_permissions',
    'sessions',
    'task_assignments',
    'task_dependencies',
    'task_templates',
    'task_timers',
    'tasks',
    'user_permissions',
    'users',
    'visit_movements',
    'visitors',
    'visits') ORDER BY table_name;
SELECT 'SELECT id, migration, batch FROM migrations ORDER BY batch, migration;' AS sql_to_run
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='migrations');
SELECT 'SELECT provider, SHA2(mailbox_key,256) AS mailbox_hash, SHA2(provider_message_id,256) AS message_hash, COUNT(*) AS duplicate_count FROM email_activities GROUP BY provider, mailbox_key, provider_message_id HAVING COUNT(*) > 1;' AS sql_to_run
WHERE (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_activities' AND column_name IN ('provider','mailbox_key','provider_message_id')) = 3;
SELECT 'SELECT provider, user_id, COUNT(*) AS duplicate_count FROM email_mailbox_connections GROUP BY provider, user_id HAVING COUNT(*) > 1;' AS sql_to_run
WHERE (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_mailbox_connections' AND column_name IN ('provider','user_id')) = 2;
SELECT 'SELECT SHA2(idempotency_key,256) AS key_hash, COUNT(*) AS duplicate_count FROM email_activities WHERE idempotency_key IS NOT NULL GROUP BY idempotency_key HAVING COUNT(*) > 1;' AS sql_to_run
WHERE EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_activities' AND column_name='idempotency_key');
-- Global duplicates are legitimate after mailbox-scoped migration; block only same-mailbox duplicates.
SELECT 'SELECT mailbox_connection_id, SHA2(idempotency_key,256) AS key_hash, COUNT(*) AS duplicate_count FROM email_activities WHERE idempotency_key IS NOT NULL AND mailbox_connection_id IS NOT NULL GROUP BY mailbox_connection_id, idempotency_key HAVING COUNT(*) > 1;' AS sql_to_run
WHERE (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_activities' AND column_name IN ('mailbox_connection_id','idempotency_key')) = 2;
SELECT 'SELECT COUNT(*) AS orphan_mailbox_owners FROM email_activities e LEFT JOIN users u ON u.id=e.mailbox_owner_user_id WHERE e.mailbox_owner_user_id IS NOT NULL AND u.id IS NULL;' AS sql_to_run
WHERE EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_activities' AND column_name='mailbox_owner_user_id')
AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='id');
SELECT 'SELECT SHA2(ob_reference_no,256) AS reference_hash, COUNT(*) AS duplicate_count FROM official_business_trips WHERE ob_reference_no IS NOT NULL GROUP BY ob_reference_no HAVING COUNT(*) > 1;' AS sql_to_run
WHERE EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='official_business_trips' AND column_name='ob_reference_no');
SELECT 'SELECT COUNT(*) AS legacy_rows_requiring_preservation_review FROM company_activities;' AS sql_to_run
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='company_activities');
-- Stage 3: only after migrations table has been confirmed. Missing history is a STOP condition.
-- This expected list is an inventory, not an instruction to apply every row.
SELECT e.migration, m.batch, CASE WHEN m.migration IS NULL THEN 'VERIFY: not recorded' ELSE 'RECORDED: compare schema/source too' END AS status
FROM (
    SELECT '0001_01_01_000000_create_users_table' AS migration
    UNION ALL
    SELECT '0001_01_01_000001_create_cache_table'
    UNION ALL
    SELECT '0001_01_01_000002_create_jobs_table'
    UNION ALL
    SELECT '2026_03_05_000001_create_companies_table'
    UNION ALL
    SELECT '2026_03_05_000003_create_contacts_table'
    UNION ALL
    SELECT '2026_03_09_052940_create_calls_table'
    UNION ALL
    SELECT '2026_03_09_052940_create_events_table'
    UNION ALL
    SELECT '2026_03_09_052940_create_tasks_table'
    UNION ALL
    SELECT '2026_03_09_052941_create_meetings_table'
    UNION ALL
    SELECT '2026_03_12_115450_add_role_to_users_table'
    UNION ALL
    SELECT '2026_03_15_222325_create_notes_table'
    UNION ALL
    SELECT '2026_03_16_000002_create_company_activities_table'
    UNION ALL
    SELECT '2026_03_16_091302_create_user_permissions_table'
    UNION ALL
    SELECT '2026_03_16_104128_create_role_permissions_table'
    UNION ALL
    SELECT '2026_03_17_000002_create_deals_table'
    UNION ALL
    SELECT '2026_03_21_134929_add_to_from_to_calls_table'
    UNION ALL
    SELECT '2026_03_21_143246_add_video_path_to_meetings_table'
    UNION ALL
    SELECT '2026_03_23_102852_add_audio_path_to_calls_table'
    UNION ALL
    SELECT '2026_03_23_115343_add_audio_path_to_meetings_table'
    UNION ALL
    SELECT '2026_04_06_153734_create_branches_table'
    UNION ALL
    SELECT '2026_04_06_153746_create_departments_table'
    UNION ALL
    SELECT '2026_04_09_000000_create_finance_records_table'
    UNION ALL
    SELECT '2026_04_09_153148_create_employees_table'
    UNION ALL
    SELECT '2026_04_12_230000_create_projects_table'
    UNION ALL
    SELECT '2026_05_11_160306_create_official_business_trips_table'
    UNION ALL
    SELECT '2026_05_13_000002_add_related_to_to_meetings_table'
    UNION ALL
    SELECT '2026_05_28_165518_create_notifications_table'
    UNION ALL
    SELECT '2026_06_05_000001_create_human_capital_logs_table'
    UNION ALL
    SELECT '2026_06_05_160000_add_human_capital_module_permissions_to_permissions_tables'
    UNION ALL
    SELECT '2026_06_06_190046_add_account_security_fields_to_users_table'
    UNION ALL
    SELECT '2026_08_14_165658_add_details_to_events_table'
    UNION ALL
    SELECT '2026_08_17_164920_add_extended_fields_to_meetings_table'
    UNION ALL
    SELECT '2026_08_17_181335_create_message_conversations_table'
    UNION ALL
    SELECT '2026_08_17_181337_create_message_entries_table'
    UNION ALL
    SELECT '2026_08_18_120000_complete_task_management_schema'
    UNION ALL
    SELECT '2026_08_18_131228_add_normalized_relationships_to_activity_tables'
    UNION ALL
    SELECT '2026_08_19_000001_create_visitors_table'
    UNION ALL
    SELECT '2026_08_19_000002_create_visits_table'
    UNION ALL
    SELECT '2026_08_19_000003_create_visit_movements_table'
    UNION ALL
    SELECT '2026_08_20_113912_add_activity_v2_core_fields_to_activity_sources'
    UNION ALL
    SELECT '2026_08_24_000001_complete_activities_v2_backend_contracts'
    UNION ALL
    SELECT '2026_08_24_000002_complete_activities_v2_safe_contracts'
    UNION ALL
    SELECT '2026_08_25_000001_create_email_activities_table'
    UNION ALL
    SELECT '2026_08_26_112720_add_actual_travel_timestamps_to_official_business_trips_table'
    UNION ALL
    SELECT '2026_08_26_130000_add_soft_deletes_to_official_business_trips_table'
    UNION ALL
    SELECT '2026_08_26_140000_add_calendar_sync_fields_to_tasks_table'
    UNION ALL
    SELECT '2026_08_26_140001_add_calendar_sync_fields_to_events_table'
    UNION ALL
    SELECT '2026_09_04_135548_drop_company_activities_table'
    UNION ALL
    SELECT '2026_09_14_135159_extend_message_entries_for_unified_channels'
    UNION ALL
    SELECT '2026_09_14_143451_create_message_entry_attachments_table'
    UNION ALL
    SELECT '2026_09_14_170000_create_messaging_provider_foundation'
    UNION ALL
    SELECT '2026_09_15_100000_create_messenger_outbound_attempts_table'
    UNION ALL
    SELECT '2026_09_16_160000_add_official_business_v3_domain_foundation'
    UNION ALL
    SELECT '2026_09_16_162000_allow_incomplete_official_business_v3_drafts'
    UNION ALL
    SELECT '2026_09_17_110100_add_official_business_v3_ticket_details'
    UNION ALL
    SELECT '2026_09_17_120000_add_official_business_v3_ticket_completion_data'
    UNION ALL
    SELECT '2026_09_17_130000_add_official_business_v3_permissions'
    UNION ALL
    SELECT '2026_09_21_000001_add_archival_metadata_to_official_business_trip_tickets'
    UNION ALL
    SELECT '2026_09_21_000002_create_company_travel_credit_ledger'
    UNION ALL
    SELECT '2026_09_28_000001_unique_official_business_master_reference'
    UNION ALL
    SELECT '2026_09_28_000002_create_official_business_master_personnel'
    UNION ALL
    SELECT '2026_09_28_235900_add_movement_status_to_official_business_trips'
    UNION ALL
    SELECT '2026_09_30_180000_create_email_mailbox_connections_table'
    UNION ALL
    SELECT '2026_09_30_180001_extend_email_activities_for_gmail'
    UNION ALL
    SELECT '2026_10_01_180000_scope_email_activities_idempotency_to_mailbox'
    UNION ALL
    SELECT '2026_10_01_180001_add_email_mailbox_sync_checkpoint'
) e LEFT JOIN migrations m ON m.migration=e.migration ORDER BY e.migration;
