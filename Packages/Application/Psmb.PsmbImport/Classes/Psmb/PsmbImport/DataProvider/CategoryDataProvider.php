<?php
namespace Psmb\PsmbImport\DataProvider;

use Ttree\ContentRepositoryImporter\DataProvider\DataProvider;
use Ttree\ContentRepositoryImporter\DataType\StringValue;

class CategoryDataProvider extends DataProvider {
  protected $result;
  /**
   * @return array
   */
  public function fetch() {
    $this->result = [];
    $this->fetchByParent();
    $this->count = count($this->result);
    return $this->result;
  }

  private function fetchByParent($parent = 0) {
    $query = $this->createQuery()
      ->select('*')
      ->from('sys_category', 'c')
      ->where('c.parent = :parent AND c.hidden=0 AND c.deleted=0')
      ->setParameter(':parent', $parent)
      ->orderBy('c.sorting');
    $statement = $query->execute();
    while ($record = $statement->fetch()) {
      $this->result[] = [
        '__externalIdentifier' => StringValue::create('c' . $record['uid'])->getValue(),
        '__parentIdentifier' => $record['parent'] ? StringValue::create('c' . $record['parent'])->getValue() : null,
        'title' => StringValue::create($record['title'])->getValue(),
        'replaceVariants' => StringValue::create($this->replaceNewlinesToCsv($record['altnames']))->getValue()
      ];
      $this->fetchByParent($record['uid']);
    }
  }

  private function replaceNewlinesToCsv($string) {
    return str_replace("\n", ", ", $string);
  }
}
