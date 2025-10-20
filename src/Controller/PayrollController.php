<?php
namespace App\Controller;

use App\Service\PayrollService;

class PayrollController
{
    private PayrollService $service;

    public function __construct()
    {
        $this->service = new PayrollService();
    }

    public function render(): void
    {
        $data = $this->service->process();
        extract($data);
        include __DIR__ . '/../../templates/_header.php';
        include __DIR__ . '/../../templates/plate.php';
        include __DIR__ . '/../../templates/_footer.php';
    }
}
