<?php
class Investor_model extends CI_Model {
    private $tableName = 'investors';
    private $relationTableName = 'investor_sportbooks';
    private $pageURL = 'investors';
    private $CI = null;

    private $dbColumns = array(
        'first_name',
        'last_name',
        'address1',
        'address2',
        'state',
        'city',
        'zip_code',
        'country',
        'email',
        'phone_number',
        'starting_bankroll',
        'notes'
    );

    private $relationDbColumns = array(
        'sportbook_id',
        'date_opened',
        'opening_balance',
        'login_name',
        'password'
    );

    function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('Investor_sportbooks_model');
    }
    public function getList($betweek){

        $result = [];
        $rows = $this->db->select('*')
            ->from($this->tableName)
            ->order_by('id','asc')
            ->get()->result_array();

        foreach ($rows as $key => $item) {
            $tmpArr = $item;
            $investorId = $item['id'];

            $tmpArr['sportbooks'] = $this->CI->Investor_sportbooks_model->getListByInvestorId($investorId,$betweek);
            $tmpArr['full_name'] = $tmpArr['first_name'] . ' ' . $tmpArr['last_name'];
            $tmpArr['current_balance'] = 0;
            foreach ($tmpArr['sportbooks'] as $sportbook_item) {
                $tmpArr['current_balance'] += $sportbook_item['current_balance_'.$betweek];
            }
            $tmpArr['custom_action'] = "<div class='action-div' data-id='".$item['id']."'><a class='sportbooks' href='/".$this->pageURL."/sportbooks?id=".$item['id']."'>Sportbooks</i></a><a class='edit' href='/".$this->pageURL."/edit?id=".$item['id']."'>Edit</i></a><a class='delete'>Delete</a></div>";

            $result[] = $tmpArr;
        }
        return $result;
    }

    public function getItem($id=null, $betweek)
    {

        $result = null;
        $row = $this->db->select('*')
            ->from($this->tableName)
            ->where(array(
                'id' => $id
            ))
            ->get()->result_array();
        if(count($row))
        {
            $result = $row[0];
            $investorId = $result['id'];
            $result['full_name'] = $result['first_name'] . ' ' . $result['last_name'];
            $result['sportbooks'] = $this->CI->Investor_sportbooks_model->getListByInvestorId($investorId,$betweek);
            $result['current_balance'] = 0;
            foreach ($result['sportbooks'] as $sportbook_item) {
                $result['current_balance'] += $sportbook_item['current_balance_'.$betweek];
            }
        }
        return $result;
    }

    public function getInvestorSportboooks($investorId, $betweek){
        $result = [];
        $sprotbookList = $this->CI->Investor_sportbooks_model->getListByInvestorId($investorId,$betweek);
        foreach ($sprotbookList as $key => $sportbook_item) {
            $tmpArr = $sportbook_item;
            $tmpArr['current_balance'] = floatval($sportbook_item['current_balance_'.$betweek]);
            $tmpArr['lastweek_balance'] = $betweek <= 1 ? 'NA': floatval($sportbook_item['current_balance_'.($betweek-1)]);
            $result[] = $tmpArr;
        }
        return $result;
    }

    private function formatRelationItem($investor_id,$data){
        $result = array(
            'investor_id' => $investor_id,
        );

        foreach ($this->relationDbColumns as $column) {
            if(!isset($data->$column))
                continue;
            if($column == 'date_opened')
                $value = date_format(date_create($data->$column),"Y-m-d");
            else
                $value = $data->$column;
            $result[$column] = $value;
        }
        return $result;
    }

    public function addItem($data)
    {
        $addDate = [];
        foreach ($this->dbColumns as $dbColumn) {
            if(isset($data[$dbColumn]))
                $addDate[$dbColumn] = $data[$dbColumn];
        }
        $this->db->insert($this->tableName, $addDate);
        $investor_id = $this->db->insert_id();

        $addSportbookDate = [];
        $sportbook_data = json_decode($data['sportbook_data']);
        foreach ($sportbook_data as $sportbook_item) {
            $addSportbookDate[] = $this->formatRelationItem($investor_id, $sportbook_item);
        }
        if(count($addSportbookDate))
            $this->db->insert_batch($this->relationTableName, $addSportbookDate);
        return true;
    }

    public function updateItem($id, $data)
    {
        $updateDate = [];
        foreach ($this->dbColumns as $dbColumn) {
            if(isset($data[$dbColumn]))
                $updateDate[$dbColumn] = $data[$dbColumn];
        }
        $this->db->where(array(
            'id' => $id
        ))->update($this->tableName,$updateDate);

        $addSportbookDate = [];
        $sportbook_data = json_decode($data['sportbook_data']);

        $validIds = [];
        foreach ($sportbook_data as $sportbook_item) {
            $rowData = $this->formatRelationItem($id, $sportbook_item);
            if($sportbook_item->relation_id == -1)
            {
                $addSportbookDate[] = $rowData;
            }else{
                $validIds[] = $sportbook_item->relation_id;
                $updateSportbookData = $rowData;
                $this->db->where(array(
                    'id' => $sportbook_item->relation_id
                ))->update($this->relationTableName,$updateSportbookData);
            }
        }
        if(count($validIds))
        {
            $this->db->where('investor_id',$id)
            ->where_not_in('id', $validIds)
            ->delete($this->relationTableName);
        }else{
            $this->db->where('investor_id',$id)
            ->delete($this->relationTableName);
        }

        if(count($addSportbookDate)){
            $this->db->insert_batch($this->relationTableName, $addSportbookDate);
        }

        return true;
    }
    
    public function deleteItem($id)
    {
       $this->db->where(array(
            'id' => $id
        ))->delete($this->tableName);
       return ture;
    } 

    public function saveSportbook($betweek,$data){
        $data = json_decode($data);
        $sportbookData = $data->data;
        foreach ($sportbookData as $sportbook_item) {
            $updateSportbookData = array(
                'current_balance_'.$betweek => $sportbook_item->current_balance
            );
            $this->db->where(array(
                'id' => $sportbook_item->id
            ))->update($this->relationTableName,$updateSportbookData);
        }
        return true;
    }   

    public function getOutcome($rules, $parlay)
    {
        $result = [];
        $initial_bet = 1000;
        if(count($parlay))
        {   
            $index = 1;
            foreach ($parlay[0] as $team) {
                $tmpArr = array(
                    'title' => 'After Bet '.$index,
                );
                if( $index == 1)
                    $before = $initial_bet;
                else
                    $before = $result[$index-2]['after'];
                if($team['line'] > 0)
                    $payout_win = $before * ($team['line']/100);
                else
                    $payout_win = $team['line'] == 0 ? 0: $before / ($team['line']/100*(-1));
                $after = $before + $payout_win;
                $tmpArr['before'] = number_format((float)$before, 2, '.', '');
                $tmpArr['payout_win'] = number_format((float)$payout_win, 2, '.', '');
                $tmpArr['after'] = number_format((float)$after, 2, '.', '');

                $result[] = $tmpArr;
                $index ++;
            }
        }
        return $result;
    }
    public function q($sql) {
        $result = $this->db->query($sql);
    }
}