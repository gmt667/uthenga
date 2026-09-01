<?php
require_once __DIR__ . '/../includes/financial_controls.php';
function financial_assert(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
financial_assert(UthengaFinancialState::normalize('authorized') === 'AUTHORIZED', 'Authorized state mapping failed.');
financial_assert(UthengaFinancialState::normalize('unknown-value') === 'UNKNOWN', 'Unknown state must fail safely.');
financial_assert(UthengaFinancialState::canTransition('created', 'pending'), 'Created to pending must be allowed.');
financial_assert(!UthengaFinancialState::canTransition('settled', 'successful'), 'Settled must not move backwards.');
financial_assert(UthengaFinancialState::mwkToMinor('7.50') === 750, 'MWK minor conversion failed.');
try { UthengaFinancialState::mwkToMinor('-1'); throw new RuntimeException('Negative amount was accepted.'); } catch (InvalidArgumentException) {}
try { UthengaFinancialState::assertTransition('unknown', 'settled'); throw new RuntimeException('Unknown transition was accepted.'); } catch (InvalidArgumentException) {}
echo "Financial control state tests passed.\n";
