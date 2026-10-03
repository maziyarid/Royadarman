<?php
// Synthetic presentation smoke test. No production environment or DB allowed.
$base = $argv[1] ?? throw new RuntimeException('isolated backend path required');
if (is_file($base.'/.env')) throw new RuntimeException('Refuse checkout with .env');
putenv('APP_ENV=testing');
putenv('APP_URL=http://127.0.0.1:8765');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('s', 32)));
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
putenv('QUEUE_CONNECTION=sync');
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->instance(App\Domain\Operations\Services\IntegrationSettings::class, new class {
    public function applyToRuntimeConfig(): void {}
});
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->make('db')->listen(static function () { throw new RuntimeException('Rendering must not query DB'); });
$request = Illuminate\Http\Request::create('http://127.0.0.1:8765/fa/panel');
$request->setLaravelSession($app->make('session')->driver());
$app->instance('request', $request);
$out = dirname($base).'/evidence';
$assert = static function ($value, $message): void { if (!$value) throw new RuntimeException($message); };
foreach (['fa','en','ar'] as $locale) {
    $app->setLocale($locale);
    foreach (['patient','coordinator','clinician','clinic_rep','owner','tech_admin'] as $role) {
        $data = ['panelKey'=>$role,'locale'=>$locale,'isDemo'=>false,'navActive'=>'dashboard','metrics'=>['cases'=>3], 'cases'=>collect()];
        $html = view('panel.dashboard', $data)->render();
        $assert(substr_count($html, '<h1>')===1, 'One page heading');
        $assert(str_contains($html, '/'.$locale.'/panel/calendar')===($role==='coordinator'), 'Calendar navigation role scope');
        $assert(str_contains($html, '/'.$locale.'/panel/analytics')===($role==='owner'), 'Analytics navigation role scope');
        $assert(str_contains($html, 'workspace-refinement.css'), 'Refinement stylesheet missing');
        file_put_contents($out.'/dashboard-'.$role.'-'.$locale.'.html', $html);
    }
}
$app->setLocale('fa');
$at=Carbon\CarbonImmutable::create(2026,3,21,10,0,0,'Asia/Tehran');
$events=collect(['task','home_service','referral_expiry'])->map(fn($kind)=>['kind'=>$kind,'at'=>$at,'url'=>'/fa/panel/tasks?status=open','title'=>'SYNTHETIC-'.$kind,'meta'=>'SYNTHETIC']);
$days=collect(range(1,31))->map(fn($i)=>['day_number'=>$i,'today'=>$i===1,'friday'=>$i%7===0,'weekday'=>'شنبه','date'=>$at->addDays($i-1),'events'=>$i===1?$events:collect()]);
$data=['panelKey'=>'coordinator','locale'=>'fa','isDemo'=>false,'navActive'=>'calendar','days'=>$days,'leading'=>0,'weekdays'=>['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه'],'jalaliMode'=>true,'monthQueryKey'=>'jmonth','previousMonth'=>'1404-12','nextMonth'=>'1405-02','monthLabel'=>'فروردین ۱۴۰۵','monthInput'=>'1405-01','events'=>$events,'summary'=>['total'=>3,'tasks'=>1,'home_service'=>1,'referral_expiry'=>1]];
$html=view('panel.calendar.index',$data)->render();
$assert(!preg_match('/<script(?:\s[^>]*)?>\s*[^<\s]/',$html),'Inline script violates CSP');
$assert(!str_contains($html,'rph98-day-empty'),'Saturday start must have zero leading cells');
$assert(substr_count($html,'data-rph98-event')===6,'Month and agenda retain every controller event');
file_put_contents($out.'/calendar-fa.html',$html);
$data['leading']=6;
$html=view('panel.calendar.index',$data)->render();
$assert(substr_count($html,'rph98-day-empty')===6,'Friday start must have six leading cells');
file_put_contents($out.'/calendar-leading-fa.html',$html);
$data['events']=collect();
$data['days']=$days->map(fn($d)=>array_replace($d,['events'=>collect()]));
$html=view('panel.calendar.index',$data)->render();
$assert(substr_count($html,'data-rph98-event')===0,'Empty month must not invent events');
file_put_contents($out.'/calendar-empty-fa.html',$html);
echo "PASS: 18 actual Blade dashboards; role-scoped calendar/analytics links; non-inline calendar JS; 0/6 leading blanks; populated/empty event rendering. No DB queries or external delivery.\n";
