<?php
/**
 * Create Mock Data / Reset Staging Ground for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ndizi_Project_Management' ) ) {
	return;
}

// Defense-in-depth: never run this destructive seeder on a production environment.
// The bundled Playground blueprints set WP_ENVIRONMENT_TYPE to 'local', so this only
// blocks real sites. (The previous extension_loaded('vrzno') Playground check was
// unreliable — vrzno is not consistently loaded in current Playground builds, which made
// this guard wp_die() in Playground and silently skip all seeding.)
if ( 'production' === wp_get_environment_type() ) {
	wp_die( 'Destructive seeding is disabled in production environments.' );
}

/*
 * The seed routine runs inside an IIFE so its working variables stay
 * function-scoped instead of leaking into the global namespace.
 */
( function () {
	global $wpdb;

// Get the current user to assign tasks and time to.
$user_id = get_current_user_id();
if ( ! $user_id ) {
	// Fallback to user ID 1, or the first administrator if 1 doesn't exist.
	$user_id = 1;
	if ( ! get_userdata( $user_id ) ) {
		$admin_users = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
			)
		);
		if ( ! empty( $admin_users ) ) {
			$user_id = $admin_users[0]->ID;
		}
	}
}

// If we still don't have a valid user, we must exit.
if ( ! get_userdata( $user_id ) ) {
	echo esc_html( "Error: Could not determine or fallback to a valid administrator user.\n" );
	exit;
}

// Ensure the primary user has billing and salary rates set so their time logs resolve correctly in reports
if ( ! get_user_meta( $user_id, '_ndizi_user_billing_rate', true ) ) {
	update_user_meta( $user_id, '_ndizi_user_billing_rate', '100.00' );
}
if ( ! get_user_meta( $user_id, '_ndizi_user_salary_rate', true ) ) {
	update_user_meta( $user_id, '_ndizi_user_salary_rate', '50.00' );
}

// Define mock user accounts to create a multi-user simulated workspace
$mock_users_definition = array(
	'alice_manager'    => array(
		'user_login'   => 'alice_manager',
		'user_pass'    => 'password123',
		'user_email'   => 'alice@example.com',
		'first_name'   => 'Alice',
		'last_name'    => 'Manager',
		'role'         => 'ndizi_manager',
		'billing_rate' => 150.00,
		'salary_rate'  => 80.00,
	),
	'bob_dev'          => array(
		'user_login'   => 'bob_dev',
		'user_pass'    => 'password123',
		'user_email'   => 'bob@example.com',
		'first_name'   => 'Bob',
		'last_name'    => 'Developer',
		'role'         => 'ndizi_team_member',
		'billing_rate' => 120.00,
		'salary_rate'  => 60.00,
	),
	'charlie_designer' => array(
		'user_login'   => 'charlie_designer',
		'user_pass'    => 'password123',
		'user_email'   => 'charlie@example.com',
		'first_name'   => 'Charlie',
		'last_name'    => 'Designer',
		'role'         => 'ndizi_team_member',
		'billing_rate' => 100.00,
		'salary_rate'  => 50.00,
	),
);

$mock_users = array();
foreach ( $mock_users_definition as $slug => $data ) {
	$existing_user = get_user_by( 'login', $data['user_login'] );
	if ( $existing_user ) {
		$u_id = $existing_user->ID;
		echo esc_html( "Found existing user: {$data['first_name']} {$data['last_name']} (ID: $u_id)\n" );
	} else {
		$u_id = wp_insert_user(
			array(
				'user_login' => $data['user_login'],
				'user_pass'  => $data['user_pass'],
				'user_email' => $data['user_email'],
				'first_name' => $data['first_name'],
				'last_name'  => $data['last_name'],
				'role'       => $data['role'],
			)
		);
		if ( is_wp_error( $u_id ) ) {
			echo esc_html( "Error creating user {$data['user_login']}: " . $u_id->get_error_message() . "\n" );
			continue;
		}
		echo esc_html( "Created User: {$data['first_name']} {$data['last_name']} (ID: $u_id)\n" );
	}

	// Update billing/salary rates
	update_user_meta( $u_id, '_ndizi_user_billing_rate', number_format( $data['billing_rate'], 2, '.', '' ) );
	update_user_meta( $u_id, '_ndizi_user_salary_rate', number_format( $data['salary_rate'], 2, '.', '' ) );

	$mock_users[ $slug ] = $u_id;
}

$alice_id   = isset( $mock_users['alice_manager'] ) ? $mock_users['alice_manager'] : $user_id;
$bob_id     = isset( $mock_users['bob_dev'] ) ? $mock_users['bob_dev'] : $user_id;
$charlie_id = isset( $mock_users['charlie_designer'] ) ? $mock_users['charlie_designer'] : $user_id;

// 1. Clear existing posts of all Ndizi post types to ensure a clean reset
$post_types = array( 'ndizi_client', 'ndizi_project', 'ndizi_task', 'ndizi_invoice', 'ndizi_contact', 'ndizi_time_off' );
foreach ( $post_types as $pt ) {
	$posts = get_posts(
		array(
			'post_type'      => $pt,
			'posts_per_page' => -1,
			'post_status'    => 'any',
		)
	);
	foreach ( $posts as $p ) {
		wp_delete_post( $p->ID, true );
	}
}
echo esc_html( "Cleaned up existing Ndizi Custom Post Type items.\n" );

// 2. Create Clients
// Client 1: Acme Corp
$client1_id = wp_insert_post(
	array(
		'post_title'   => 'Acme Corp',
		'post_content' => 'Acme Corporation - Global logistics and widget supply.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_client',
	)
);
if ( $client1_id ) {
	update_post_meta( $client1_id, '_ndizi_client_website', 'https://acme.org' );
	update_post_meta( $client1_id, '_ndizi_client_address', "123 Acme Way\nSuite 100\nMetropolis, NY 10001" );
	update_post_meta( $client1_id, '_ndizi_client_status', 'active' );
	update_post_meta( $client1_id, '_ndizi_client_auth_key', 'acme-token-123' );
	echo esc_html( "Created Client: Acme Corp (ID: $client1_id)\n" );
}

// Client 2: Stark Industries
$client2_id = wp_insert_post(
	array(
		'post_title'   => 'Stark Industries',
		'post_content' => 'Stark Industries - Advanced defense systems and clean energy technologies.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_client',
	)
);
if ( $client2_id ) {
	update_post_meta( $client2_id, '_ndizi_client_website', 'https://starkindustries.com' );
	update_post_meta( $client2_id, '_ndizi_client_address', "10880 Wilshire Blvd\nLos Angeles, CA 90024" );
	update_post_meta( $client2_id, '_ndizi_client_status', 'active' );
	update_post_meta( $client2_id, '_ndizi_client_auth_key', 'stark-token-456' );
	echo esc_html( "Created Client: Stark Industries (ID: $client2_id)\n" );
}

// Client 3: Wayne Enterprises (Archived)
$client3_id = wp_insert_post(
	array(
		'post_title'   => 'Wayne Enterprises',
		'post_content' => 'Wayne Enterprises - Global technology, defense, and venture capital.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_client',
	)
);
if ( $client3_id ) {
	update_post_meta( $client3_id, '_ndizi_client_website', 'https://wayneenterprises.com' );
	update_post_meta( $client3_id, '_ndizi_client_address', "Wayne Tower\nGotham City, NJ 07101" );
	update_post_meta( $client3_id, '_ndizi_client_status', 'archived' );
	update_post_meta( $client3_id, '_ndizi_client_auth_key', 'wayne-token-789' );
	echo esc_html( "Created Client: Wayne Enterprises (ID: $client3_id)\n" );
}

// 3. Create Contacts
// Contact 1: Pepper Potts (Stark Industries)
$contact1_id = wp_insert_post(
	array(
		'post_title'   => 'Pepper Potts',
		'post_content' => 'Chief Executive Officer',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_contact',
	)
);
if ( $contact1_id ) {
	update_post_meta( $contact1_id, '_ndizi_contact_email', 'pepper@stark.com' );
	update_post_meta( $contact1_id, '_ndizi_contact_phone', '555-0199' );
	update_post_meta( $contact1_id, '_ndizi_contact_role', 'CEO' );
	update_post_meta( $contact1_id, '_ndizi_associated_clients', array( $client2_id ) );
	echo esc_html( "Created Contact: Pepper Potts (ID: $contact1_id)\n" );
}

// Contact 2: Bruce Wayne (Wayne Enterprises)
$contact2_id = wp_insert_post(
	array(
		'post_title'   => 'Bruce Wayne',
		'post_content' => 'Majority Shareholder & Chairman',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_contact',
	)
);
if ( $contact2_id ) {
	update_post_meta( $contact2_id, '_ndizi_contact_email', 'bruce@waynecorp.com' );
	update_post_meta( $contact2_id, '_ndizi_contact_phone', '555-0144' );
	update_post_meta( $contact2_id, '_ndizi_contact_role', 'Chairman' );
	update_post_meta( $contact2_id, '_ndizi_associated_clients', array( $client3_id ) );
	echo esc_html( "Created Contact: Bruce Wayne (ID: $contact2_id)\n" );
}

// Contact 3: Lucius Fox (Wayne Enterprises)
$contact3_id = wp_insert_post(
	array(
		'post_title'   => 'Lucius Fox',
		'post_content' => 'Business Manager and Tech Lead',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_contact',
	)
);
if ( $contact3_id ) {
	update_post_meta( $contact3_id, '_ndizi_contact_email', 'lucius@waynecorp.com' );
	update_post_meta( $contact3_id, '_ndizi_contact_phone', '555-0182' );
	update_post_meta( $contact3_id, '_ndizi_contact_role', 'Business Manager' );
	update_post_meta( $contact3_id, '_ndizi_associated_clients', array( $client3_id ) );
	echo esc_html( "Created Contact: Lucius Fox (ID: $contact3_id)\n" );
}

// Contact 4: John Doe (Acme & Stark)
$contact4_id = wp_insert_post(
	array(
		'post_title'   => 'John Doe',
		'post_content' => 'General Operations consultant representing multiple companies.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_contact',
	)
);
if ( $contact4_id ) {
	update_post_meta( $contact4_id, '_ndizi_contact_email', 'john.doe@gmail.com' );
	update_post_meta( $contact4_id, '_ndizi_contact_phone', '555-0123' );
	update_post_meta( $contact4_id, '_ndizi_contact_role', 'Project Director' );
	update_post_meta( $contact4_id, '_ndizi_associated_clients', array( $client1_id, $client2_id ) );
	echo esc_html( "Created Contact: John Doe (ID: $contact4_id)\n" );
}

// 4. Create Projects
// Project 1: Acme Website Redesign (Client 1)
$proj1_id = wp_insert_post(
	array(
		'post_title'   => 'Acme Website Redesign',
		'post_content' => 'Complete redesign and development of corporate portal with responsive design and client portal integrations.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_project',
	)
);
if ( $proj1_id ) {
	update_post_meta( $proj1_id, '_ndizi_client_id', $client1_id );
	update_post_meta( $proj1_id, '_ndizi_project_start_date', '2026-06-01' );
	update_post_meta( $proj1_id, '_ndizi_project_end_date', '2026-06-30' );
	update_post_meta( $proj1_id, '_ndizi_project_budget', 5000.00 );
	update_post_meta( $proj1_id, '_ndizi_project_status', 'active' );
	echo esc_html( "Created Project: Acme Website Redesign (ID: $proj1_id)\n" );
}

// Project 2: Acme Mobile App (Client 1)
$proj2_id = wp_insert_post(
	array(
		'post_title'   => 'Acme Mobile App',
		'post_content' => 'Create a native iOS and Android package tracking app for logistics clients.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_project',
	)
);
if ( $proj2_id ) {
	update_post_meta( $proj2_id, '_ndizi_client_id', $client1_id );
	update_post_meta( $proj2_id, '_ndizi_project_start_date', '2026-06-10' );
	update_post_meta( $proj2_id, '_ndizi_project_end_date', '2026-07-31' );
	update_post_meta( $proj2_id, '_ndizi_project_budget', 12000.00 );
	update_post_meta( $proj2_id, '_ndizi_project_status', 'active' );
	echo esc_html( "Created Project: Acme Mobile App (ID: $proj2_id)\n" );
}

// Project 3: Stark Arc Reactor Portal (Client 2)
$proj3_id = wp_insert_post(
	array(
		'post_title'   => 'Stark Arc Reactor Portal',
		'post_content' => 'Design a secure database dashboard linking thermal outputs across reactor models.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_project',
	)
);
if ( $proj3_id ) {
	update_post_meta( $proj3_id, '_ndizi_client_id', $client2_id );
	update_post_meta( $proj3_id, '_ndizi_project_start_date', '2026-06-01' );
	update_post_meta( $proj3_id, '_ndizi_project_end_date', '2026-08-31' );
	update_post_meta( $proj3_id, '_ndizi_project_budget', 250000.00 );
	update_post_meta( $proj3_id, '_ndizi_project_status', 'active' );
	echo esc_html( "Created Project: Stark Arc Reactor Portal (ID: $proj3_id)\n" );
}

// Project 4: Stark Clean Energy Grid (Client 2 - Archived)
$proj4_id = wp_insert_post(
	array(
		'post_title'   => 'Stark Clean Energy Grid',
		'post_content' => 'Initial scoping and timeline mapping of metropolitan energy routing grids.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_project',
	)
);
if ( $proj4_id ) {
	update_post_meta( $proj4_id, '_ndizi_client_id', $client2_id );
	update_post_meta( $proj4_id, '_ndizi_project_start_date', '2026-05-01' );
	update_post_meta( $proj4_id, '_ndizi_project_end_date', '2026-05-31' );
	update_post_meta( $proj4_id, '_ndizi_project_budget', 80000.00 );
	update_post_meta( $proj4_id, '_ndizi_project_status', 'archived' );
	echo esc_html( "Created Project: Stark Clean Energy Grid (ID: $proj4_id)\n" );
}

// Project 5: Wayne Batcave Security Audit (Client 3 - Archived)
$proj5_id = wp_insert_post(
	array(
		'post_title'   => 'Wayne Batcave Security Audit',
		'post_content' => 'Comprehensive server intrusion prevention scans and biometric entry logging analysis.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_project',
	)
);
if ( $proj5_id ) {
	update_post_meta( $proj5_id, '_ndizi_client_id', $client3_id );
	update_post_meta( $proj5_id, '_ndizi_project_start_date', '2026-04-15' );
	update_post_meta( $proj5_id, '_ndizi_project_end_date', '2026-05-15' );
	update_post_meta( $proj5_id, '_ndizi_project_budget', 45000.00 );
	update_post_meta( $proj5_id, '_ndizi_project_status', 'archived' );
	echo esc_html( "Created Project: Wayne Batcave Security Audit (ID: $proj5_id)\n" );
}

// 5. Create Tasks
// Project 1 Tasks
$task1_id = wp_insert_post(
	array(
		'post_title'   => 'Design Home Page Layout',
		'post_content' => 'Create gorgeous high-fidelity wireframes and design mockups for client approval.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_task',
	)
);
if ( $task1_id ) {
	update_post_meta( $task1_id, '_ndizi_project_id', $proj1_id );
	update_post_meta( $task1_id, '_ndizi_assigned_user_id', $charlie_id );
	update_post_meta( $task1_id, '_ndizi_task_status', 'in_progress' );
	update_post_meta( $task1_id, '_ndizi_task_priority', 'high' );
	update_post_meta( $task1_id, '_ndizi_task_due_date', '2026-06-15' );
	echo esc_html( "Created Task: Design Home Page Layout (ID: $task1_id)\n" );
}

$task2_id = wp_insert_post(
	array(
		'post_title'   => 'Database Setup',
		'post_content' => 'Configure WordPress Custom Table schema and build custom REST endpoints.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_task',
	)
);
if ( $task2_id ) {
	update_post_meta( $task2_id, '_ndizi_project_id', $proj1_id );
	update_post_meta( $task2_id, '_ndizi_assigned_user_id', $user_id );
	update_post_meta( $task2_id, '_ndizi_task_status', 'open' );
	update_post_meta( $task2_id, '_ndizi_task_priority', 'medium' );
	update_post_meta( $task2_id, '_ndizi_task_due_date', '2026-06-18' );
	echo esc_html( "Created Task: Database Setup (ID: $task2_id)\n" );
}

$task3_id = wp_insert_post(
	array(
		'post_title'   => 'Setup REST API Endpoints',
		'post_content' => 'Build rest-controllers and implement secure applications key validations.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_task',
	)
);
if ( $task3_id ) {
	update_post_meta( $task3_id, '_ndizi_project_id', $proj1_id );
	update_post_meta( $task3_id, '_ndizi_assigned_user_id', $user_id );
	update_post_meta( $task3_id, '_ndizi_task_status', 'completed' );
	update_post_meta( $task3_id, '_ndizi_task_priority', 'medium' );
	update_post_meta( $task3_id, '_ndizi_task_due_date', '2026-06-08' );
	echo esc_html( "Created Task: Setup REST API Endpoints (ID: $task3_id)\n" );
}

// Project 2 Tasks
$task4_id = wp_insert_post(
	array(
		'post_title'   => 'Push Notifications Integration',
		'post_content' => 'Integrate Firebase notifications SDK and configure dashboard trigger events.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_task',
	)
);
if ( $task4_id ) {
	update_post_meta( $task4_id, '_ndizi_project_id', $proj2_id );
	update_post_meta( $task4_id, '_ndizi_assigned_user_id', $bob_id );
	update_post_meta( $task4_id, '_ndizi_task_status', 'open' );
	update_post_meta( $task4_id, '_ndizi_task_priority', 'high' );
	update_post_meta( $task4_id, '_ndizi_task_due_date', '2026-07-10' );
	echo esc_html( "Created Task: Push Notifications Integration (ID: $task4_id)\n" );
}

$task5_id = wp_insert_post(
	array(
		'post_title'   => 'App Store Submission',
		'post_content' => 'Compile final binary distributions and configure store graphics settings.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_task',
	)
);
if ( $task5_id ) {
	update_post_meta( $task5_id, '_ndizi_project_id', $proj2_id );
	update_post_meta( $task5_id, '_ndizi_assigned_user_id', $alice_id );
	update_post_meta( $task5_id, '_ndizi_task_status', 'open' );
	update_post_meta( $task5_id, '_ndizi_task_priority', 'low' );
	update_post_meta( $task5_id, '_ndizi_task_due_date', '2026-07-28' );
	echo esc_html( "Created Task: App Store Submission (ID: $task5_id)\n" );
}

// Project 3 Tasks
$task6_id = wp_insert_post(
	array(
		'post_title'   => 'Core Thermal Monitoring UI',
		'post_content' => 'Build the graphical Canvas chart displaying heat distribution in the shell.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_task',
	)
);
if ( $task6_id ) {
	update_post_meta( $task6_id, '_ndizi_project_id', $proj3_id );
	update_post_meta( $task6_id, '_ndizi_assigned_user_id', $bob_id );
	update_post_meta( $task6_id, '_ndizi_task_status', 'in_progress' );
	update_post_meta( $task6_id, '_ndizi_task_priority', 'high' );
	update_post_meta( $task6_id, '_ndizi_task_due_date', '2026-07-15' );
	echo esc_html( "Created Task: Core Thermal Monitoring UI (ID: $task6_id)\n" );
}

$task7_id = wp_insert_post(
	array(
		'post_title'   => 'Vibranium Shielding Check',
		'post_content' => 'Verify energy dispersal rates along the containment grid.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_task',
	)
);
if ( $task7_id ) {
	update_post_meta( $task7_id, '_ndizi_project_id', $proj3_id );
	update_post_meta( $task7_id, '_ndizi_assigned_user_id', $charlie_id );
	update_post_meta( $task7_id, '_ndizi_task_status', 'completed' );
	update_post_meta( $task7_id, '_ndizi_task_priority', 'high' );
	update_post_meta( $task7_id, '_ndizi_task_due_date', '2026-06-05' );
	echo esc_html( "Created Task: Vibranium Shielding Check (ID: $task7_id)\n" );
}

// 6. Create Invoices
// Invoice 1: Acme Redesign Deposit
$inv1_id = wp_insert_post(
	array(
		'post_title'   => 'Invoice #1001',
		'post_content' => 'Deposit payment for initial wireframing and design mocks.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_invoice',
	)
);
if ( $inv1_id ) {
	update_post_meta( $inv1_id, '_ndizi_project_id', $proj1_id );
	update_post_meta( $inv1_id, '_ndizi_invoice_date', '2026-06-05' );
	update_post_meta( $inv1_id, '_ndizi_invoice_due_date', '2026-06-20' );
	update_post_meta( $inv1_id, '_ndizi_invoice_amount', 1500.00 );
	update_post_meta( $inv1_id, '_ndizi_invoice_status', 'paid' );
	echo esc_html( "Created Invoice: #1001 (ID: $inv1_id)\n" );
}

// Invoice 2: Acme Redesign Iteration 2
$inv2_id = wp_insert_post(
	array(
		'post_title'   => 'Invoice #1002',
		'post_content' => 'Milestone payment for database schemas and REST controller hookups.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_invoice',
	)
);
if ( $inv2_id ) {
	update_post_meta( $inv2_id, '_ndizi_project_id', $proj1_id );
	update_post_meta( $inv2_id, '_ndizi_invoice_date', '2026-06-10' );
	update_post_meta( $inv2_id, '_ndizi_invoice_due_date', '2026-06-25' );
	update_post_meta( $inv2_id, '_ndizi_invoice_amount', 2000.00 );
	update_post_meta( $inv2_id, '_ndizi_invoice_status', 'sent' );
	echo esc_html( "Created Invoice: #1002 (ID: $inv2_id)\n" );
}

// Invoice 3: Stark Phase 1 Approval
$inv3_id = wp_insert_post(
	array(
		'post_title'   => 'Invoice #2001',
		'post_content' => 'Initial staging and vibranium integrity testing milestones.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_invoice',
	)
);
if ( $inv3_id ) {
	update_post_meta( $inv3_id, '_ndizi_project_id', $proj3_id );
	update_post_meta( $inv3_id, '_ndizi_invoice_date', '2026-06-01' );
	update_post_meta( $inv3_id, '_ndizi_invoice_due_date', '2026-06-15' );
	update_post_meta( $inv3_id, '_ndizi_invoice_amount', 75000.00 );
	update_post_meta( $inv3_id, '_ndizi_invoice_status', 'paid' );
	echo esc_html( "Created Invoice: #2001 (ID: $inv3_id)\n" );
}

// Invoice 4: Stark Phase 2 Setup
$inv4_id = wp_insert_post(
	array(
		'post_title'   => 'Invoice #2002',
		'post_content' => 'Monitoring controls setup and sensor calibration logs.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_invoice',
	)
);
if ( $inv4_id ) {
	update_post_meta( $inv4_id, '_ndizi_project_id', $proj3_id );
	update_post_meta( $inv4_id, '_ndizi_invoice_date', '2026-06-10' );
	update_post_meta( $inv4_id, '_ndizi_invoice_due_date', '2026-06-24' );
	update_post_meta( $inv4_id, '_ndizi_invoice_amount', 50000.00 );
	update_post_meta( $inv4_id, '_ndizi_invoice_status', 'draft' );
	echo esc_html( "Created Invoice: #2002 (ID: $inv4_id)\n" );
}

// 7. Create Time Entries in Custom Table
global $wpdb;
$table_name = Ndizi_DB::get_table_name();

// Clean up existing entries first to avoid duplicates.
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derives from $wpdb->prefix and cannot be a placeholder; one-off TRUNCATE of the custom table while seeding Playground demo data.
$wpdb->query( "TRUNCATE TABLE $table_name" );

// Define a list of logs to insert
$time_entries = array(
	// Project 1 (Acme Redesign) - Invoiced to Invoice #1001 (inv1)
	array(
		'project_id'  => $proj1_id,
		'task_id'     => $task1_id,
		'user_id'     => $charlie_id,
		'description' => 'Initial Layout Mockup Design and feedback cycles',
		'start_time'  => '2026-06-03 09:00:00',
		'end_time'    => '2026-06-03 14:00:00',
		'billable'    => 1,
		'invoice_id'  => $inv1_id,
		'approved'    => 1,
	),
	array(
		'project_id'  => $proj1_id,
		'task_id'     => $task1_id,
		'user_id'     => $charlie_id,
		'description' => 'Home Page wireframe corrections and client sync',
		'start_time'  => '2026-06-04 10:00:00',
		'end_time'    => '2026-06-04 13:00:00',
		'billable'    => 1,
		'invoice_id'  => $inv1_id,
		'approved'    => 1,
	),
	// Project 1 - Invoiced to Invoice #1002 (inv2)
	array(
		'project_id'  => $proj1_id,
		'task_id'     => $task2_id,
		'user_id'     => $user_id,
		'description' => 'Configure custom tables and hook init callbacks',
		'start_time'  => '2026-06-06 10:00:00',
		'end_time'    => '2026-06-06 13:00:00',
		'billable'    => 1,
		'invoice_id'  => $inv2_id,
		'approved'    => 1,
	),
	array(
		'project_id'  => $proj1_id,
		'task_id'     => $task3_id,
		'user_id'     => $user_id,
		'description' => 'Implement token-authentications checks in REST handlers',
		'start_time'  => '2026-06-07 14:00:00',
		'end_time'    => '2026-06-07 18:30:00',
		'billable'    => 1,
		'invoice_id'  => $inv2_id,
		'approved'    => 1,
	),
	// Project 1 - Uninvoiced & Billable
	array(
		'project_id'  => $proj1_id,
		'task_id'     => $task2_id,
		'user_id'     => $user_id,
		'description' => 'Database migrations audit and schema indexes configuration',
		'start_time'  => '2026-06-09 09:30:00',
		'end_time'    => '2026-06-09 11:30:00',
		'billable'    => 1,
		'invoice_id'  => 0,
	),
	// Project 2 (Acme Mobile App) - Uninvoiced & Billable
	array(
		'project_id'  => $proj2_id,
		'task_id'     => $task4_id,
		'user_id'     => $bob_id,
		'description' => 'Setup Firebase project console and export google-services plist config',
		'start_time'  => '2026-06-10 11:00:00',
		'end_time'    => '2026-06-10 14:00:00',
		'billable'    => 1,
		'invoice_id'  => 0,
	),
	// Project 2 - Uninvoiced & Non-Billable
	array(
		'project_id'  => $proj2_id,
		'task_id'     => $task5_id,
		'user_id'     => $alice_id,
		'description' => 'App store developer account review and documentation lookup',
		'start_time'  => '2026-06-10 15:00:00',
		'end_time'    => '2026-06-10 16:30:00',
		'billable'    => 0,
		'invoice_id'  => 0,
	),
	// Project 3 (Stark Arc Reactor Portal) - Invoiced to Invoice #2001 (inv3)
	array(
		'project_id'  => $proj3_id,
		'task_id'     => $task7_id,
		'user_id'     => $charlie_id,
		'description' => 'Run disperse containment integrity testing scans',
		'start_time'  => '2026-06-02 08:00:00',
		'end_time'    => '2026-06-02 18:00:00',
		'billable'    => 1,
		'invoice_id'  => $inv3_id,
		'approved'    => 1,
	),
	// Project 3 - Uninvoiced & Billable
	array(
		'project_id'  => $proj3_id,
		'task_id'     => $task6_id,
		'user_id'     => $bob_id,
		'description' => 'Render core Canvas container component and test updates latency',
		'start_time'  => '2026-06-08 09:00:00',
		'end_time'    => '2026-06-08 17:00:00',
		'billable'    => 1,
		'invoice_id'  => 0,
		'approved'    => 1,
	),
	// Project 4 (Stark Energy Grid) - Uninvoiced (Archived project)
	array(
		'project_id'  => $proj4_id,
		'task_id'     => 0,
		'user_id'     => $alice_id,
		'description' => 'Scoped grids route overlays and timelines outline',
		'start_time'  => '2026-05-15 10:00:00',
		'end_time'    => '2026-05-15 15:30:00',
		'billable'    => 1,
		'invoice_id'  => 0,
	),
	// Project 5 (Wayne Batcave security) - Uninvoiced (Archived project)
	array(
		'project_id'  => $proj5_id,
		'task_id'     => 0,
		'user_id'     => $alice_id,
		'description' => 'Scanned server ports and audited mainframe logins frequencies',
		'start_time'  => '2026-04-20 22:00:00',
		'end_time'    => '2026-04-21 02:00:00',
		'billable'    => 1,
		'invoice_id'  => 0,
	),
);

foreach ( $time_entries as $entry ) {
	$duration = strtotime( $entry['end_time'] ) - strtotime( $entry['start_time'] );
	$result   = Ndizi_Time_Service::log_time_manual(
		$entry['user_id'],
		$entry['project_id'],
		array(
			'task_id'     => $entry['task_id'],
			'description' => $entry['description'],
			'duration'    => $duration,
			'billable'    => $entry['billable'],
			'start_time'  => $entry['start_time'],
			'end_time'    => $entry['end_time'],
		)
	);

	if ( is_wp_error( $result ) ) {
		continue;
	}

	// Apply post-insert metadata (invoice link and/or manager approval) in a single update.
	$post_insert = array();
	if ( $entry['invoice_id'] ) {
		$post_insert['invoice_id'] = $entry['invoice_id'];
	}
	if ( ! empty( $entry['approved'] ) ) {
		$post_insert['approved']    = 1;
		$post_insert['approved_by'] = $alice_id;
	}
	if ( $post_insert ) {
		Ndizi_DB::update_time_entry( $result, $post_insert );
	}
}

echo esc_html( 'Logged ' . count( $time_entries ) . " time entries via Time Service.\n" );

// Start a live, still-running timer for Bob so the dashboard shows an active entry.
$active_timer = Ndizi_Time_Service::start_timer(
	$bob_id,
	$proj2_id,
	array(
		'task_id'     => $task4_id,
		'description' => 'Currently debugging push-notification delivery (live timer)',
		'billable'    => 1,
	)
);
if ( is_wp_error( $active_timer ) ) {
	echo esc_html( 'Skipped active timer for Bob: ' . $active_timer->get_error_message() . "\n" );
} else {
	echo esc_html( "Started a live running timer for Bob (ID: $active_timer)\n" );
}

// Lock everything on or before 2026-05-31 so the archived-project entries demonstrate
// the locked-period state. Set last, since inserts above would be rejected in a locked range.
update_option( 'ndizi_lock_date', '2026-05-31' );
echo esc_html( "Set accounting lock date to 2026-05-31 (archived May/April entries are now locked)\n" );

// 8. Create Time Off Requests
$time_off_1 = wp_insert_post(
	array(
		'post_title'   => 'Vacation Request - John Doe',
		'post_content' => 'Annual family vacation.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_time_off',
	)
);
if ( $time_off_1 ) {
	update_post_meta( $time_off_1, '_ndizi_time_off_start_date', '2026-07-01' );
	update_post_meta( $time_off_1, '_ndizi_time_off_end_date', '2026-07-10' );
	update_post_meta( $time_off_1, '_ndizi_time_off_type', 'vacation' );
	update_post_meta( $time_off_1, '_ndizi_time_off_status', 'approved' );
	update_post_meta( $time_off_1, '_ndizi_time_off_client_id', $client1_id );
	echo esc_html( "Created Time Off Request: Vacation - John Doe (ID: $time_off_1)\n" );
}

$time_off_2 = wp_insert_post(
	array(
		'post_title'   => 'Sick Leave - Jane Smith',
		'post_content' => 'Recovering from illness.',
		'post_status'  => 'publish',
		'post_type'    => 'ndizi_time_off',
	)
);
if ( $time_off_2 ) {
	update_post_meta( $time_off_2, '_ndizi_time_off_start_date', '2026-06-15' );
	update_post_meta( $time_off_2, '_ndizi_time_off_end_date', '2026-06-16' );
	update_post_meta( $time_off_2, '_ndizi_time_off_type', 'sick_leave' );
	update_post_meta( $time_off_2, '_ndizi_time_off_status', 'pending' );
	update_post_meta( $time_off_2, '_ndizi_time_off_client_id', $client2_id );
	echo esc_html( "Created Time Off Request: Sick Leave - Jane Smith (ID: $time_off_2)\n" );
}

// 9. Create Client Portal Page if it doesn't exist
$portal_page = get_page_by_path( 'client-portal' );
if ( ! $portal_page ) {
	$portal_id = wp_insert_post(
		array(
			'post_title'   => 'Client Portal',
			'post_content' => '<!-- wp:ndizi/client-portal /-->',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_name'    => 'client-portal',
		)
	);
	echo esc_html( "Created Client Portal Page (ID: $portal_id)\n" );
} else {
	// If it exists, make sure it has the block editor layout
	wp_update_post(
		array(
			'ID'           => $portal_page->ID,
			'post_content' => '<!-- wp:ndizi/client-portal /-->',
		)
	);
	echo esc_html( "Client Portal Page verified/updated.\n" );
}

echo esc_html( "Staging mock data population complete.\n" );

} )();
