<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';
function tie_vendor_profile_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$draft = UthengaTieVendorProfileContracts::transport(['profile_name' => 'Example Express', 'vehicle_type' => 'bus', 'origin' => 'Lilongwe', 'destination' => 'Blantyre', 'pickup_location' => 'Bus Depot', 'departure_time' => '07:00', 'fare_per_seat' => 18000, 'total_seats' => 30, 'schedule_days' => ['Monday', 'Tuesday']]);
tie_vendor_profile_assert($draft['total_seats'] === 30 && $draft['vehicle_type'] === 'bus', 'Transport profile accepts a compact, validated operating setup.');
$rejected = false; try { UthengaTieVendorProfileContracts::transport(array_replace($draft, ['origin' => 'Lilongwe', 'destination' => 'Lilongwe'])); } catch (UthengaTieException $error) { $rejected = $error->type() === 'validation_error'; }
tie_vendor_profile_assert($rejected, 'Transport profile rejects an invalid same-place route.');
$invalidLifecycle = false; try { UthengaTieVendorServiceLifecycle::assertTransition(UthengaTieVendorServiceLifecycle::PRIVATE_DRAFT, UthengaTieVendorServiceLifecycle::ACTIVE); } catch (UthengaTieException $error) { $invalidLifecycle = $error->type() === 'validation_error'; }
tie_vendor_profile_assert($invalidLifecycle, 'A private draft cannot become active without setup, review, and publication.');
UthengaTieVendorServiceLifecycle::assertTransition(UthengaTieVendorServiceLifecycle::SETUP_INCOMPLETE, UthengaTieVendorServiceLifecycle::READY_FOR_REVIEW);
UthengaTieVendorServiceLifecycle::assertTransition(UthengaTieVendorServiceLifecycle::READY_FOR_REVIEW, UthengaTieVendorServiceLifecycle::PUBLISHED);
UthengaTieVendorServiceLifecycle::assertTransition(UthengaTieVendorServiceLifecycle::PUBLISHED, UthengaTieVendorServiceLifecycle::ACTIVE);
$assistant = new UthengaTieVendorProfileDraftService(new UthengaTieLlmGateway(new UthengaTieUnavailableLlmProvider()));
$proposal = $assistant->transport('I run a 30-seat bus from Lilongwe to Blantyre at 07:00 for MWK 18000.');
tie_vendor_profile_assert($proposal['draft']['vehicle_type'] === 'bus' && $proposal['draft']['total_seats'] === '30' && $proposal['confirmation_required'] === true, 'Agent draft falls back safely and always requires vendor confirmation.');
echo "TIE vendor operating-profile tests passed.\n";
