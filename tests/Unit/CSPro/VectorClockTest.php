<?php

namespace Tests\Unit\CSPro;

use AppBundle\CSPro\VectorClock;
use PHPUnit\Framework\TestCase;

class VectorClockTest extends TestCase
{
    public function testConstructorWithNull(): void
    {
        $vc = new VectorClock(null);
        $this->assertSame(0, $vc->getVersion('anyDevice'));
    }

    public function testIncrement(): void
    {
        $vc = new VectorClock(null);
        $vc->increment('dev1');
        $this->assertSame(1, $vc->getVersion('dev1'));
        $vc->increment('dev1');
        $this->assertSame(2, $vc->getVersion('dev1'));
    }

    public function testSetVersion(): void
    {
        $vc = new VectorClock(null);
        $vc->setVersion('dev1', 42);
        $this->assertSame(42, $vc->getVersion('dev1'));
    }

    public function testGetJSONClockString(): void
    {
        $vc = new VectorClock(null);
        $vc->setVersion('deviceA', 3);
        $vc->setVersion('deviceB', 7);

        $json = $vc->getJSONClockString();
        $decoded = json_decode($json, true);

        $this->assertCount(2, $decoded);
        $this->assertSame('deviceA', $decoded[0]['deviceId']);
        $this->assertSame(3, $decoded[0]['revision']);
        $this->assertSame('deviceB', $decoded[1]['deviceId']);
        $this->assertSame(7, $decoded[1]['revision']);
    }

    public function testCopyViaJson(): void
    {
        $vc1 = new VectorClock(null);
        $vc1->setVersion('dev1', 5);
        $vc1->setVersion('dev2', 10);

        $jsonString = $vc1->getJSONClockString();
        $jsonArray = json_decode($jsonString, true);
        $vc2 = new VectorClock($jsonArray);

        $this->assertTrue($vc1->IsEqual($vc2));
        $this->assertSame(5, $vc2->getVersion('dev1'));
        $this->assertSame(10, $vc2->getVersion('dev2'));
    }

    public function testMerge(): void
    {
        $vc1 = new VectorClock(null);
        $vc1->setVersion('dev1', 3);
        $vc1->setVersion('dev2', 7);

        $vc2 = new VectorClock(null);
        $vc2->setVersion('dev1', 5);
        $vc2->setVersion('dev3', 2);

        $vc1->merge($vc2);

        $this->assertSame(5, $vc1->getVersion('dev1'));
        $this->assertSame(7, $vc1->getVersion('dev2'));
        $this->assertSame(2, $vc1->getVersion('dev3'));
    }

    public function testIsEqual(): void
    {
        $vc1 = new VectorClock(null);
        $vc1->setVersion('dev1', 3);
        $vc1->setVersion('dev2', 7);

        $vc2 = new VectorClock(null);
        $vc2->setVersion('dev1', 3);
        $vc2->setVersion('dev2', 7);

        $this->assertTrue($vc1->IsEqual($vc2));
        $this->assertTrue($vc2->IsEqual($vc1));
    }

    public function testIsLessThanDescendant(): void
    {
        $parent = new VectorClock(null);
        $parent->setVersion('dev1', 3);
        $parent->setVersion('dev2', 5);

        $child = new VectorClock(null);
        $child->setVersion('dev1', 3);
        $child->setVersion('dev2', 6);

        $this->assertTrue($parent->IsLessThan($child));
        $this->assertFalse($child->IsLessThan($parent));
    }

    public function testIsLessThanEmpty(): void
    {
        $empty = new VectorClock(null);

        $nonEmpty = new VectorClock(null);
        $nonEmpty->setVersion('dev1', 1);

        $this->assertTrue($empty->IsLessThan($nonEmpty));
        $this->assertFalse($nonEmpty->IsLessThan($empty));
    }

    public function testIsLessThanEquals(): void
    {
        $vc1 = new VectorClock(null);
        $vc1->setVersion('dev1', 3);

        $vc2 = new VectorClock(null);
        $vc2->setVersion('dev1', 3);

        $this->assertFalse($vc1->IsLessThan($vc2));
        $this->assertFalse($vc2->IsLessThan($vc1));
    }

    public function testIsLessThanConflicting(): void
    {
        $vc1 = new VectorClock(null);
        $vc1->setVersion('dev1', 5);
        $vc1->setVersion('dev2', 3);

        $vc2 = new VectorClock(null);
        $vc2->setVersion('dev1', 3);
        $vc2->setVersion('dev2', 5);

        $this->assertFalse($vc1->IsLessThan($vc2));
        $this->assertFalse($vc2->IsLessThan($vc1));
    }

    public function testIsLessThanDisjoint(): void
    {
        $vc1 = new VectorClock(null);
        $vc1->setVersion('dev1', 1);

        $vc2 = new VectorClock(null);
        $vc2->setVersion('dev2', 1);

        // Disjoint clocks: each has a device the other doesn't know about
        // vc1 has dev1=1, vc2 has dev2=1
        // vc1->IsLessThan(vc2): dev1 in vc1 → vc2.getVersion(dev1)=0 < 1 → returns false
        $this->assertFalse($vc1->IsLessThan($vc2));
        $this->assertFalse($vc2->IsLessThan($vc1));
    }

    public function testGetAllDevices(): void
    {
        $vc = new VectorClock(null);
        $vc->increment('dev1');
        $vc->increment('dev2');
        $vc->increment('dev3');

        $devices = $vc->getAllDevices();
        $this->assertCount(3, $devices);
        $this->assertContains('dev1', $devices);
        $this->assertContains('dev2', $devices);
        $this->assertContains('dev3', $devices);
    }

    public function testCompare(): void
    {
        $vc1 = new VectorClock(null);
        $vc1->setVersion('dev1', 1);

        $vc2 = new VectorClock(null);
        $vc2->setVersion('dev1', 2);

        $this->assertSame(0, $vc1->compare($vc1));
        $this->assertSame(-1, $vc1->compare($vc2));
        $this->assertSame(1, $vc2->compare($vc1));
    }
}
