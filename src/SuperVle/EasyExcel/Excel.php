<?php

namespace SuperVle\EasyExcel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class Excel
{
    protected $file_name;

    public function __construct()
    {
        $this->file_name = time();
    }

    public function setFileName($file_name)
    {
        $this->file_name = $file_name;

    }

    public function output($data, $title, $field, $fileType = 'Xlsx',$file_or_url = 'file',$more_table = false)
    {

        $spreadsheet = new Spreadsheet();



        //获取当前表
        $sheet = $spreadsheet->getActiveSheet();

        $this->write_excel($sheet,$data, $title, $field);

        if($more_table){
            if(isset($more_table['data']) && isset($more_table['field'])){
                $spreadsheet->createSheet();
                //获取当前表
                $sheet = $spreadsheet->setActiveSheetIndex(1);
                $this->write_excel($sheet,$more_table['data'], $more_table['title'], $more_table['field']);
            }else{
                if(is_array($more_table)){
                    foreach($more_table as $k => $v){
                        if(isset($v['data']) && isset($v['field'])){
                            $spreadsheet->createSheet();
                            //获取当前表
                            $sheet = $spreadsheet->setActiveSheetIndex($k+1);
                            $this->write_excel($sheet,$v['data'], $v['title'], $v['field']);
                        }
                    }
                }
            }
        }



        $writer = IOFactory::createWriter($spreadsheet, $fileType);

        $fileName = $this->file_name;
        if($file_or_url === 'file'){
            $this->excelBrowserExport($fileName, $fileType);
            $writer->save('php://output');
        }else{
            $filename = $file_or_url . '/' . $fileName .'.'. $fileType;
            $writer->save($filename);
            return '/'.$filename;
        }
    }

    protected function write_excel($sheet,$data,$title,$field){
        $en       = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $end_en   = $en[count($field) - 1];
        $end_line = count($data) + 2;


        //表标题
        if($title){
            //表名称
            $sheet->setTitle($title);

            $sheet->setCellValue('A1', $title);
            $sheet->getRowDimension('1')->setRowHeight(30);
            //合并
            $sheet->mergeCells('A1:' . $end_en . '1');
            $styleArrayBody = [
                //字体
                'font' => [
                    //加粗
                    'bold'  => true,
                    //颜色
                    'color' => ['rgb' => '000000'],
                    //字体大小
                    'size'  => 16,
                    //字体名称
                    'name'  => 'Verdana'
                ]
            ];
            $sheet->getStyle('A1:' . $end_en . '1')->applyFromArray($styleArrayBody);//A1:J2 设置样色

            /*整体样式*/
            $styleArrayBody = [
                //边框
                'borders'   => [
                    'allBorders' => [
                        //边框
                        'borderStyle' => Border::BORDER_THIN,
                        //颜色
                        'color'       => ['argb' => '666666'],
                    ],
                ],
                //居中
                'alignment' => [
                    //水平居中
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    //垂直居中
                    'vertical'   => Alignment::VERTICAL_CENTER
                ],
            ];
            $sheet->getStyle('A1:' . $end_en . $end_line)->applyFromArray($styleArrayBody);//A1:J2 设置样色
        }

        if($title){
            $field_line = '2';
        }else{
            $field_line = '1';
        }

        //表头
        $num = 0;
        foreach ($field as $i => $o) {
            //设置表头
            $sheet->setCellValue($en[$num] . $field_line, $o['value']);
            //列宽
            $sheet->getColumnDimension($en[$num])->setWidth($o['width']);
            $num++;
        }

        //表体
        $num = 0;
        foreach ($field as $i => $o) {
            foreach ($data as $k => $datum) {
                $value = '';
                if (isset($o['with']) && $o['with'] && isset($datum[$o['with']][$i])) {
                    /*通过with拿数据*/
                    $value = $datum[$o['with']][$i];
                } else if (isset($datum[$i])) {
                    /*直接拿数据*/
                    $value = $datum[$i];
                }

                if (isset($o['type']) && $o['type'] == 'string') {
                    /*字符串类型后面加空格*/
                    $value = $value . ' ';
                }
                if (isset($o['type']) && $o['type'] == 'array' && is_array($value)) {
                    /*数组类型用'、'分隔*/
                    $value = implode('、',$value);
                }
                if (isset($o['max']) &&  is_numeric($o['max']) && $o['max'] > 0) {
                    /*长数据截取*/
                    if (mb_strlen($value) < $o['max']) {
                        $res_value = $value;
                    } else {
                        $res_value = substr($value, 0, $o['max']);
                    }
                    $value = $res_value;
                }

                $data_line = (int)$k + 1 + (int)$field_line;
                $sheet->setCellValue($en[$num] . $data_line, $value);


                if(isset($o['color'])){
                    $styleArrayBody = [
                        //字体
                        'font' => [
                            //颜色
                            'color' => ['rgb' => $o['color']],
                        ]
                    ];
                    $sheet->getStyle($en[$num] . $data_line)->applyFromArray($styleArrayBody);
                }
            }
            $num++;
        }
    }


    /**
     * 导入Excel表取出需要的内容
     * @param $excelPath //excel表路径
     * @param $param //每列对应数据键名及标题 ['A' => ['key' => 'A',title => '标题名称']] 标题名为空则不验证
     * @param $startRow //内容开始的行
     * @return array    //返回数据内容 [['A' => 'content']];
     * @throws \PhpOffice\PhpSpreadsheet\Calculation\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function input($excelPath, $param, $startRow,$del_file = true)
    {
        $excelObj = IOFactory::load($excelPath);
        if (!$excelObj) {
            return $this->error('加载Excel表失败，请检查Excel内容');
        }
        $excelWorkSheet = $excelObj->getActiveSheet();
        $rowCount       = $excelWorkSheet->getHighestRow();
        if ($rowCount <= 0) {
            return $this->error('Excel表内容为空。');
        }
        //验证标题
        foreach ($param as $column => $content) {
            $item = $excelWorkSheet->getCell($column . ($startRow - 1))->getCalculatedValue();
            if ($item != $content['title'] && !empty($content['title'])) {
                return $this->error('请检查模板标题是否正确。');
            }
        }
        $excelData = array();
        for ($row = $startRow; $row <= $rowCount; $row++) {
            $rowData = array();
            foreach ($param as $column => $content) {
                $item                     = $excelWorkSheet->getCell($column . $row)->getCalculatedValue();
                $rowData[$content['key']] = $item;
            }
            if (!implode('', $rowData)) {
                continue;//删除空行
            }
            $excelData[] = $rowData;
        }
        if($del_file){
            unlink($excelPath);
        }
        return $this->success($excelData);
    }


    protected function error($info)
    {
        return ['msg' => $info, 'status' => false, 'data' => false];
    }

    protected function success($data)
    {
        return ['msg' => 'ok', 'status' => true, 'data' => $data];
    }


    /**
     * 输出到浏览器(需要设置header头)
     * @param string $fileName 文件名
     * @param string $fileType 文件类型
     */
    protected function excelBrowserExport($fileName, $fileType)
    {

        //文件名称校验
        if (!$fileName) {
            trigger_error('文件名不能为空', E_USER_ERROR);
        }

        //Excel文件类型校验
        $type = ['Excel2007', 'Xlsx', 'Excel5', 'xls'];
        if (!in_array($fileType, $type)) {
            trigger_error('未知文件类型', E_USER_ERROR);
        }

        if ($fileType == 'Excel2007' || $fileType == 'Xlsx') {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
            header('Cache-Control: max-age=0');
        } else { //Excel5
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $fileName . '.xls"');
            header('Cache-Control: max-age=0');
        }
    }
}
