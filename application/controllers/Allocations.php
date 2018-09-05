<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Allocations extends CI_Controller {

    public function __construct() {
        parent::__construct();
        
        $this->load->library('authlibrary');    
        if (!$this->authlibrary->loggedin()) {
            redirect('login');
        }

        $this->load->model('Investor_sportbooks_model', 'model');
        $this->load->model('Investor_model', 'investor_model');
        $this->load->model('WorkSheet_model', 'worksheet_model');
        $this->load->model('Settings_model', 'setting_model');
        $this->load->library('session');
    }
    public function index()
    {
        $date = new DateTime(date('Y-m-d'));
        $betweek = $date->format('W');
        $investors = $this->investor_model->getIdList();
        $investorId = isset($_GET['investorId'])? $_GET['investorId'] : $investors[0]['id'] ;

        $data['betweek'] = isset($_SESSION['betday']) ? $_SESSION['betday'] :$betweek;
        $data['investors'] = $investors;
        $data['investorId'] = $investorId;
        $data['pageType'] = 'allocations';
        $data['pageTitle'] = 'Allocation of Money';

        $this->load->view('allocations',$data);
    }

    public function loadSportbooks(){
        $investorId = $_POST['investorId'];
        $betweek = $_POST['betweek'];
        $_SESSION['betday'] = $betweek;
        
        $worksheet = $this->worksheet_model->getRROrders($betweek,$investorId);
        $bets = getBetArr($worksheet);
        $setting = $this->setting_model->getActiveSettingByInvestor($betweek,$investorId);
        $investor_sportbooks = $this->investor_model->getInvestorSportboooksWithBets($investorId, $betweek);

        $total_balance = 0;
        foreach ($investor_sportbooks as $item) {
            $total_balance += $item['current_balance'];
        }

        $allocation_percent = $setting['data']['rr_allocation'];
        $optimal_balance = $total_balance * $allocation_percent / 100;

        $total_m_number = 0;
        foreach ($worksheet['data']['rr'] as $item) {
            $total_m_number += $item['m_number'];
        }

        $hypo_bet_amount = $total_m_number ? $optimal_balance / $total_m_number : 1;
        $hypo_bet_amount = roundBetAmount($hypo_bet_amount);
        $data['data'] = $investor_sportbooks;
        $data['current_bet_amount'] = $setting['data']['bet_amount'];
        $data['hypo_bet_amount'] = $hypo_bet_amount;
        header('Content-Type: application/json');
        echo json_encode( $data);
    }

    public function assign()
    {
        $investorId = $_POST['investorId'];
        $betweek = $_POST['betweek'];
        $bet_amount = $_POST['bet_amount'];
        $result = $this->investor_model->assign($investorId, $betweek, $bet_amount);
        header('Content-Type: application/json');
        echo json_encode( $result);
    }
}
