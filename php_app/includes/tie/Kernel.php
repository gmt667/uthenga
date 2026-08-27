<?php

final class UthengaTieKernel
{
    public UthengaTieContextModule $context;
    public UthengaTieTravelContextService $travelContext;
    public UthengaTieQueryModule $query;
    public UthengaTieAvailabilityModule $availability;
    public UthengaTieBudgetModule $budget;
    public UthengaTieConflictModule $conflicts;
    public UthengaTieRecommendationModule $recommendation;
    public UthengaTiePlanModule $plans;
    public UthengaTieBookingModule $booking;
    public UthengaTiePaymentModule $payments;
    public UthengaTieLocationModule $location;
    public UthengaTieNearbySearchService $nearby;
    public UthengaTieRoutingModule $routing;
    public UthengaTieJourneyModule $journey;
    public UthengaTieConversationModule $conversation;
    public UthengaTieNotificationModule $notifications;
    public UthengaTiePromptModule $prompts;
    public UthengaTieTripPlanningModule $tripPlanning;
    public UthengaTieValidationModule $validation;
    public UthengaTieLlmGateway $llm;
    public UthengaTieCoordinationService $coordination;
    public UthengaTieTransportPaymentService $transportPayments;
    public UthengaTieTripEngineService $trips;
    public UthengaTiePassengerService $passengers;
    public UthengaTieMessagingService $messaging;
    public UthengaTieEarningsService $earnings;
    public UthengaTieVehicleService $vehicle;
    public UthengaTieScheduleService $schedule;
    public UthengaTieReportsService $reports;
    public UthengaTieDriverSettingsService $driverSettings;
    public UthengaTieVendorProfileService $vendorProfiles;
    public UthengaTieVendorProfileDraftService $vendorProfileDrafts;
    public UthengaTieCustomerBookingsService $customerBookings;
    public UthengaTieTripCollaborationService $tripCollaboration;
    public UthengaTieTripMessagingService $tripMessages;
    public UthengaTieSavedPlacesService $savedPlaces;
    public UthengaTieCustomerDocumentsService $customerDocuments;
    public UthengaTieCustomerPreferencesService $customerPreferences;
    public UthengaTieCustomerPaymentMethodsService $customerPaymentMethods;
    public UthengaTieBusOperationsService $busOperations;
    public UthengaTieBusFinanceService $busFinance;
    public UthengaTieBusFleetService $busFleet;
    public UthengaTieBusSettingsService $busSettings;

    public function __construct()
    {
        $this->context = new UthengaTieContextService();
        global $pdo;
        $this->query = new UthengaTieQueryService($pdo instanceof PDO ? $pdo : null);
        $this->availability = new UthengaTieAvailabilityService($this->query);
        $this->budget = new UthengaTieBudgetService();
        $this->conflicts = new UthengaTieConflictService();
        $this->recommendation = new UthengaTieRecommendationService();
        $reverseGeocoding = new UthengaTieReverseGeocodingService(UthengaTieGeocodingProviderFactory::configured());
        $locationEngine = new UthengaTieLocationEngine($reverseGeocoding);
        $this->location = new UthengaTieLocationService($locationEngine);
        $this->nearby = new UthengaTieNearbySearchService(new UthengaTieMariaDbGeographicSearchProvider($this->query), $this->availability, $locationEngine);
        $this->travelContext = new UthengaTieTravelContextService($pdo instanceof PDO ? $pdo : null, $this->query, $this->availability, $this->location);
        $this->tripCollaboration = new UthengaTieTripCollaborationService($pdo instanceof PDO ? $pdo : null);
        $this->plans = new UthengaTieTripPlanningEngine($pdo instanceof PDO ? $pdo : null, $this->travelContext, $this->recommendation, $this->query, $this->availability, $this->budget, $this->conflicts, $this->tripCollaboration);
        $this->tripMessages = new UthengaTieTripMessagingService($pdo instanceof PDO ? $pdo : null, $this->tripCollaboration);
        $this->savedPlaces = new UthengaTieSavedPlacesService($pdo instanceof PDO ? $pdo : null);
        $this->customerDocuments = new UthengaTieCustomerDocumentsService($pdo instanceof PDO ? $pdo : null, $this->tripCollaboration);
        $this->customerPreferences = new UthengaTieCustomerPreferencesService($pdo instanceof PDO ? $pdo : null);
        $this->customerPaymentMethods = new UthengaTieCustomerPaymentMethodsService($pdo instanceof PDO ? $pdo : null, UthengaTiePaychanguGatewayFactory::configured());
        $this->busOperations = new UthengaTieBusOperationsService($pdo instanceof PDO ? $pdo : null, UthengaTiePaychanguGatewayFactory::configured(), $this->customerPaymentMethods);
        $this->busFinance = new UthengaTieBusFinanceService($pdo instanceof PDO ? $pdo : null, UthengaTiePaychanguGatewayFactory::configured());
        $this->busFleet = new UthengaTieBusFleetService($pdo instanceof PDO ? $pdo : null);
        $this->busSettings = new UthengaTieBusSettingsService($pdo instanceof PDO ? $pdo : null);
        $this->booking = new UthengaTieBookingOrchestrator($pdo instanceof PDO ? $pdo : null, $this->plans, $this->query, $this->availability, UthengaTieMarketplaceBookingProviderFactory::configured());
        $inventoryHolds = $pdo instanceof PDO ? new UthengaTieMariaDbInventoryHoldProvider($pdo) : new UthengaTieUnavailableInventoryHoldProvider();
        $this->payments = new UthengaTiePaymentService($pdo instanceof PDO ? $pdo : null, $this->plans, $this->budget, UthengaTiePaychanguGatewayFactory::configured(), $inventoryHolds, $pdo instanceof PDO ? new UthengaTieMariaDbBookingCommitProvider($pdo, $inventoryHolds) : new UthengaTieUnavailableBookingCommitProvider());
        $this->routing = new UthengaTieRoutingService(UthengaTieRoutingProviderFactory::configured());
        $this->journey = new UthengaTieJourneyService($pdo instanceof PDO ? $pdo : null);
        $this->coordination = new UthengaTieCoordinationService($pdo instanceof PDO ? $pdo : null);
        $this->transportPayments = new UthengaTieTransportPaymentService($pdo instanceof PDO ? $pdo : null, UthengaTiePaychanguGatewayFactory::configured());
        $this->trips = new UthengaTieTripEngineService($pdo instanceof PDO ? $pdo : null);
        $this->passengers = new UthengaTiePassengerService($pdo instanceof PDO ? $pdo : null);
        $this->messaging = new UthengaTieMessagingService($pdo instanceof PDO ? $pdo : null);
        $this->earnings = new UthengaTieEarningsService($pdo instanceof PDO ? $pdo : null);
        $this->vehicle = new UthengaTieVehicleService($pdo instanceof PDO ? $pdo : null);
        $this->schedule = new UthengaTieScheduleService($pdo instanceof PDO ? $pdo : null, $this->trips);
        $this->reports = new UthengaTieReportsService($pdo instanceof PDO ? $pdo : null);
        $this->driverSettings = new UthengaTieDriverSettingsService($pdo instanceof PDO ? $pdo : null);
        $this->vendorProfiles = new UthengaTieVendorProfileService($pdo instanceof PDO ? $pdo : null);
        $this->customerBookings = new UthengaTieCustomerBookingsService($pdo instanceof PDO ? $pdo : null);
        $this->notifications = new UthengaTieNotificationService($pdo instanceof PDO ? $pdo : null);
        $this->prompts = new UthengaTiePromptService();
        $this->validation = new UthengaTieValidationService();
        $this->tripPlanning = new UthengaTieTripPlanningService($this->query, $this->validation);
        $this->llm = new UthengaTieLlmGateway(UthengaTieLlmProviderFactory::configured());
        $this->vendorProfileDrafts = new UthengaTieVendorProfileDraftService($this->llm);
        $this->conversation = new UthengaTieConversationService(
            $this->travelContext,
            $this->recommendation,
            $this->budget,
            $this->llm,
            new UthengaTieConversationMemory(),
            new UthengaTieAiPromptBuilder(),
            new UthengaTieAiResponseValidator()
        );
    }
}
