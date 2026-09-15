<?php

namespace Tests\Unit;

use App\Support\PanelDemoRegistry;
use PHPUnit\Framework\TestCase;

final class PanelDemoRegistryTest extends TestCase
{
    public function test_clinic_demo_key_is_stable_and_not_a_display_name(): void
    {
        $this->assertSame('royadarman.panel-demo.clinic', PanelDemoRegistry::CLINIC_DEMO_KEY);
        $this->assertNotSame(PanelDemoRegistry::CLINIC_DISPLAY_NAME, PanelDemoRegistry::CLINIC_DEMO_KEY);
        $this->assertSame('TEST Demo Clinic', PanelDemoRegistry::CLINIC_DISPLAY_NAME);
    }

    public function test_case_reference_prefix_is_explicit(): void
    {
        $this->assertTrue(PanelDemoRegistry::isDemoCaseReference('TEST-DEMO-OPG-001'));
        $this->assertTrue(PanelDemoRegistry::isDemoCaseReference(PanelDemoRegistry::HOME_CASE_REFERENCE));
        $this->assertSame(3, count(PanelDemoRegistry::caseReferences()));
        $this->assertFalse(PanelDemoRegistry::isDemoCaseReference('REAL-CASE-MUST-NOT-LEAK'));
        $this->assertFalse(PanelDemoRegistry::isDemoCaseReference(null));
        $this->assertFalse(PanelDemoRegistry::isDemoCaseReference(''));
    }

    public function test_six_fixed_identities_are_registered(): void
    {
        $this->assertSame(['admin', 'client', 'clinic', 'coordinator', 'clinician', 'tech'], PanelDemoRegistry::aliases());
        foreach (PanelDemoRegistry::identities() as $identity) {
            $this->assertStringEndsWith('@royadarman.invalid', $identity['email']);
            $this->assertStringStartsWith('TEST ', $identity['name']);
        }
    }
}
