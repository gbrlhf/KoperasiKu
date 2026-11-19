<?php

namespace Tests\Feature;

use Database\Seeders\PicBDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PicBDemoLoginPgsqlTest extends TestCase
{
    use DatabaseTransactions;

    public function test_demo_member_can_login_and_reseed_preserves_stock(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());
        $legacy = \App\Models\User::where('email', 'ahmad.fauzi@polman-bandung.ac.id')->first()
            ?? \App\Models\User::create(['email' => 'ahmad.fauzi@polman-bandung.ac.id', 'password_hash' => \Illuminate\Support\Facades\Hash::make('old-password'), 'status' => 'active']);
        $this->seed(PicBDemoSeeder::class);
        $this->assertTrue($legacy->fresh()->hasRole('MEMBER'));
        $member = DB::table('members')->where('user_id', DB::table('users')->where('email', 'ahmad.fauzi@polman-bandung.ac.id')->value('id'))->first();
        $this->assertNotNull($member);
        $this->assertTrue(DB::table('member_credit_accounts')->where('member_id', $member->id)->exists());
        $product = DB::table('products')->where('sku', 'SEM-001')->first();
        $this->assertNotNull($product);
        $balance = DB::table('inventory_balances')->where('product_id', $product->id);
        $balance->increment('on_hand');
        $expected = $balance->value('on_hand');
        $this->seed(PicBDemoSeeder::class);
        $this->assertEquals($expected, $balance->value('on_hand'));
        $this->postJson('/login', ['username' => 'ahmad.fauzi@polman-bandung.ac.id', 'password' => 'wrong', 'role' => 'MEMBER'])->assertUnauthorized();
        $this->assertGuest();
        $this->postJson('/login', ['username' => $member->employee_number, 'password' => 'password123', 'role' => 'MEMBER'])
            ->assertOk()->assertJsonPath('user.member_id', (string) $member->id);
    }
    public function test_demo_roles_and_live_pages(): void
    {
        $this->seed(PicBDemoSeeder::class);
        $this->withoutVite()->get('/')->assertOk()->assertSee('Beras Premium Ramos');
        foreach ([['ahmad.fauzi@polman-bandung.ac.id','password123','MEMBER','/'], ['kasir@polman-bandung.ac.id','kasir123','CASHIER','/cashier/piutang'], ['manajer@polman-bandung.ac.id','manajer123','MANAGER','/manager/dashboard']] as [$email,$password,$role,$url]) {
            $this->postJson('/login', ['username'=>$email,'password'=>$password,'role'=>$role])->assertOk()->assertJsonPath('role',$role);
            $this->get($url)->assertOk();
            $this->postJson('/logout')->assertOk();
        }
    }
}
