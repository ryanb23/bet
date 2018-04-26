<?php
class Settings_model extends CI_Model {
    private $tableName = 'settings';
    private $headerName = array(
        'Bet Allocation %',
        'Round Robbin Structure',
        'Parlays',
        'Individual Bets(Picks)',
    );

    private $defaultSetting;
    private $defaultResult;
    private $fomularData;

    private $numberOfTeams;
    private $numberOfPicks;


    function __construct()
    {
        $this->numberOfTeams = array(
            'min' => 3,
            'max' => 8
        );

        $this->numberOfPicks = array(
            'min' => 2,
            'max' => 8
        );

        $this->defaultSetting = array(
            array(
                'title' => $this->headerName[0],
                'bet_percent'    => null
            ),
            array(
                'title' => $this->headerName[1],
                'bet_percent'    => null,
                'bet_number1'    => null,
                'bet_text'       => 'by',
                'bet_number2'    => null,
                'bet_number3'    => null,
                'bet_number4'    => null,
            ),
            array(
                'title' => $this->headerName[2],
                'bet_percent'    => null,
                'bet_number1'    => null,
            ),
            array(
                'title' => $this->headerName[3],
                'bet_percent'    => null,
                'bet_number1'    => null,
            )
        );

        $this->defaultResult = array(
            array(
                'parlay'        => null,
                'sheet'         => null,
                'bet_number'    => null
            ),
            array(
                'parlay'        => null,
                'sheet'         => null,
                'bet_number'    => null
            ),array(
                'parlay'        => null,
                'sheet'         => null,
                'bet_number'    => null
            ),
        );

        $fomularData = array();

        for($i = $this->numberOfTeams['min']; $i <= $this->numberOfTeams['max']; $i++)
        {
            for($j = $this->numberOfPicks['min']; $j <= $this->numberOfTeams['max']; $j++)
            {
                if($i >= $j)
                    $fomularData[$i][$j] = $this->combinationValue($i,$j);
                else
                    $fomularData[$i][$j] = '';
            }
        }
        $this->fomularData = $fomularData;
    }
    public function getNumberOfPicks(){
        return $this->numberOfPicks;
    }

    public function getNumberOfTeams(){
        return $this->numberOfTeams;
    }

    public function getFomular(){
        return $this->fomularData;
    }

    private function factorialize($num) {
        if ($num < 0) 
            return -1;
        else if ($num == 0) 
            return 1;
        else {
            return ($num * $this->factorialize($num - 1));
        }
    }

    private function combinationValue($n, $r){
        return $this->factorialize($n) / ( $this->factorialize($r) * $this->factorialize($n-$r));
    }

    public function getGroupUserList($categoryType){
        $result = array();
        switch ($categoryType) {
            case 1:
                $CI =& get_instance();
                $CI->load->model('groups_model');
                $result = $CI->groups_model->getAll();
                break;
            case 2:
                $CI =& get_instance();
                $CI->load->model('users_model');
                $result = $CI->users_model->getAll();
                break;
            case 0:
            default:
                # code...
                break;
        }
        return $result;
    }

    public function getActiveSetting($betday, $settingId = -1){
        $query = $this->db->select('*')
            ->from('settings')
            ->where(array(
                'betday' => $betday
            ));
        if($settingId != -1)
        {
            $query = $query->where('id', $settingId);
        }else{
            $query = $query->where('active', 1);
        }
        $rows = $query->get()->result_array();

        $result = array(
            'type'          => 0,
            'groupuser_id'  => null,
            'rr_number1'    => 0,
            'rr_number2'    => 0,
            'rr_number3'    => 0,
            'rr_number4'    => 0
        );

        if(count($rows))
        {
            $data = $rows[0];
            $result['type'] = $data['type'];
            $result['groupuser_id'] = $data['groupuser_id'];
            $result['rr_number1'] = $data['rr_number1'];
            $result['rr_number2'] = $data['rr_number2'];
            $result['rr_number3'] = $data['rr_number3'];
            $result['rr_number4'] = $data['rr_number4'];
        }
        return $result;
    }

    public function getSettingList($betday){
        $query = $this->db->select('*')
            ->from('settings')
            ->where(array(
                'betday' => $betday
            ))->order_by('type','asc')
            ->order_by('groupuser_id','asc');

        $CI =& get_instance();
        $CI->load->model('groups_model');
        $CI->load->model('users_model');

        $rows = $query->get()->result_array();
        foreach($rows as $key => &$item)
        {
            switch ($item['type']) {
                case '1':
                    $group = $CI->groups_model->getByID($item['groupuser_id']);
                    $item['title'] = $group['name'];
                    break;
                case '2':
                    $user = $CI->users_model->getByID($item['groupuser_id']);
                    $item['title'] = $user['name'];
                    break;
                case '0':
                default:
                    $item['title'] = 'All';
                    break;
            }
            foreach($item as $column_name => &$value)
            {
                $value = (is_null($value) || empty($value)) ? 'NA' : $value;
            }
        }
        return $rows;
    }

    public function getSettings($betday, $categoryType, $categoryGroupUser){

        $fomularData = $this->fomularData;
        $settings = $this->defaultSetting;
        $description = '';
        $bet_analysis = $this->defaultResult;

        $CI =& get_instance();
        $CI->load->model('picks_model');
        $candy_data = $CI->picks_model->getIndividual($betday, 'candy');
        $pick_data = $CI->picks_model->getIndividual($betday, 'pick');

        $CI =& get_instance();
        $CI->load->model('worksheet_model');
        $rr_disableCnt = $CI->worksheet_model->getDisableCount($betday);
        $parlayCnt = $CI->worksheet_model->getParlayCount($betday);

        $individualCnt = 0;
        foreach($pick_data as $key => $item)
        {
            if($item['selected'])
            {
                $individualCnt++;
            }
        }

        $query = $this->db->select('*')
            ->from('settings')
            ->where(array(
                'betday' => $betday,
                'type'  => $categoryType
            ));
        if($categoryType != 0)
            $query->where('groupuser_id', $categoryGroupUser);

        $rows = $query->get()->result_array();

        if(count($rows))
        {
            $data = $rows[0];
            // var_dump($data);die;
            $settings[0]['bet_percent'] = $data['bet_allocation'];

            $settings[1]['bet_percent'] = $data['rr_allocation'];            
            $settings[1]['bet_number1'] = $data['rr_number1'];
            $settings[1]['bet_number2'] = $data['rr_number2'];
            $settings[1]['bet_number3'] = $data['rr_number3'];
            $settings[1]['bet_number4'] = $data['rr_number4'];

            $settings[2]['bet_percent'] = $data['parlay_allocation'];            
            $settings[2]['bet_number1'] = $parlayCnt;            

            $settings[3]['bet_percent'] = $data['pick_allocation'];            
            $settings[3]['bet_number1'] = $individualCnt;            

            $bet_analysis[0]['parlay'] = @$fomularData[$data['rr_number1']][$data['rr_number2']] + @$fomularData[$data['rr_number1']][$data['rr_number3']] + @$fomularData[$data['rr_number1']][$data['rr_number4']];
            $bet_analysis[0]['sheet'] = (7) * count($candy_data) - $rr_disableCnt;
            $bet_analysis[0]['bet_number'] = $bet_analysis[0]['parlay'] * $bet_analysis[0]['sheet'];

            $bet_analysis[1]['parlay'] = 1;
            $bet_analysis[1]['sheet'] = $parlayCnt;
            $bet_analysis[1]['bet_number'] = $bet_analysis[1]['parlay'] * $bet_analysis[1]['sheet'];

            $bet_analysis[2]['parlay'] = 1;
            $bet_analysis[2]['sheet'] = $individualCnt;
            $bet_analysis[2]['bet_number'] = $bet_analysis[2]['parlay'] * $bet_analysis[2]['sheet'];

            $description = $data['description'];
        }

        $result = array(
            'bet_allocation'    => $settings,
            'bet_analysis'      => $bet_analysis,
            'description'       => $description
        );

        return $result;
    }

    public function saveSettings($betday, $categoryType, $categoryGroupUser,$data, $description = ''){
        

        $jsonData = json_decode($data);
        $settingData = $jsonData->data;
        $description = $jsonData->description;

        $query = $this->db->from('settings')
            ->where(array(
                'betday'    => $betday,
                'type'      => $categoryType,
            ));
        if($categoryType != 0)
            $query = $query->where('groupuser_id', $categoryGroupUser);
        $rows = $query->get();

        $newData = array(
            'bet_allocation' => $settingData[0]['1'],
            'rr_allocation' => $settingData[1]['1'],
            'rr_number1' => $settingData[1]['2'],
            'rr_number2' => $settingData[1]['4'],
            'rr_number3' => $settingData[1]['5'],
            'rr_number4' => $settingData[1]['6'],
            'parlay_allocation' => $settingData[2]['1'],
            'parlay_number1' => $settingData[2]['2'],
            'pick_allocation' => $settingData[3]['1'],
            'pick_number1'  => $settingData[3]['2'],
            'description'   => $description,
            'active'        => 1,
        );

        //toggle active
        $toggleQuery = $this->db->where(array(
                'betday'    => $betday,
                'active'      => 1,
            ));
        $toggleQuery = $toggleQuery->update('settings', array('active' => '0'));

        if ( $rows->num_rows() > 0 ) 
        {
            $updateQuery = $this->db->where(array(
                'betday'    => $betday,
                'type'      => $categoryType,
            ));
            if($categoryType != 0)
                $updateQuery = $updateQuery->where('groupuser_id', $categoryGroupUser);

            $updateQuery->update('settings', $newData);
        } else {
            $newData['betday'] = $betday;
            $newData['type'] = $categoryType;
            if($categoryType != 0)
                $newData['groupuser_id'] = $categoryGroupUser;
            $this->db->insert('settings', $newData);
        }
    }

    public function q($sql) {
        $result = $this->db->query($sql);
    }
}