<?php
namespace Wtpdf\Ubl\Lib;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( !class_exists( '\\Wtpdf\\Ubl\\Lib\\ArrayToXmlConverter')) {

    class ArrayToXmlConverter
    {
        /**
         * Check if an array contains a required child element with a non-empty value
         * 
         * @param array $value_array The array to check
         * @param string $required_child_name The name of the required child element (e.g., 'cbc:ID')
         * @return bool True if the required child exists with a non-empty value
         */
        private function has_required_child( $value_array, $required_child_name ) {
            if ( ! is_array( $value_array ) ) {
                return false;
            }
            
            foreach ( $value_array as $child ) {
                if ( isset( $child['name'] ) && $child['name'] === $required_child_name ) {
                    // Check if the value is not empty
                    if ( isset( $child['value'] ) ) {
                        if ( is_array( $child['value'] ) ) {
                            // For nested arrays, check recursively
                            return $this->has_required_child( $child['value'], $required_child_name );
                        } else {
                            // For scalar values, check if not empty
                            return ( $child['value'] !== '' && $child['value'] !== null );
                        }
                    }
                }
                // Also check nested arrays
                if ( is_array( $child ) && isset( $child['value'] ) && is_array( $child['value'] ) ) {
                    if ( $this->has_required_child( $child['value'], $required_child_name ) ) {
                        return true;
                    }
                }
            }
            
            return false;
        }
        
        public function convertToXml( $data, &$xmlconvert ) {
    
            $namespaces = array(
                'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'xmlns:cbc' => "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
            );
            foreach( $data as $item ) {

                if ( is_array( $item ) && !isset( $item['name'] ) && !isset( $item['value'] ) ) {
                    $item_arr_values = array_values( $item );
                    $this->convertToXml( $item_arr_values, $xmlconvert );
                }
                
                // Check if name is set and not empty, and value is set (allow empty strings and 0 for tax elements)
                if ( isset( $item['name'] ) && !empty( $item['name'] ) && isset( $item['value'] ) ) {
                    // Elements that require cbc:ID child element (must have valid ID or be skipped)
                    $elements_requiring_id = array(
                        'cac:OriginatorDocumentReference',
                        'cac:ContractDocumentReference',
                    );
                    
                    // Check if this element requires cbc:ID
                    if ( in_array( $item['name'], $elements_requiring_id ) ) {
                        // If value is empty string, skip the element
                        if ( ! is_array( $item['value'] ) && ( $item['value'] === '' || $item['value'] === null ) ) {
                            continue; // Skip this element entirely
                        }
                        // If value is an array but doesn't have valid cbc:ID, skip the element
                        if ( is_array( $item['value'] ) && ! $this->has_required_child( $item['value'], 'cbc:ID' ) ) {
                            continue; // Skip this element entirely
                        }
                    }
                    
                    // For arrays, process them
                    if ( is_array( $item['value'] ) ) {
                        $attr = array();
                        if ( isset( $item['attributes'] ) && is_array( $item['attributes'] ) ) {
                            $attr = $item['attributes'];
                        }
                        $parent_node = $xmlconvert->add( $item['name'], null, $attr );  
                        $this->convertToXml( $item['value'], $parent_node );
                    } else if ( !is_array( $item['value'] ) && !is_object( $item['value'] ) ) {
                        // Skip empty strings for date fields (they must be valid dates or omitted)
                        $is_date_field = ( strpos( $item['name'], 'Date' ) !== false );
                        if ( $is_date_field && ( $item['value'] === '' || $item['value'] === null ) ) {
                            // Skip empty date fields
                            continue;
                        }
                        
                        // For scalar values, include them even if empty string or 0 (needed for tax elements)
                        // Only skip if value is null (and not a date field, which we already handled)
                        if ( $item['value'] !== null ) {
                            $attr = array();
                            if ( isset( $item['attributes'] ) && is_array( $item['attributes'] ) ) {
                                $attr = $item['attributes'];
                            }
                            $xmlconvert->add( $item['name'], $item['value'], $attr );
                        }
                    }
                }
            }
            return $xmlconvert->asXML();
        }
    }

}
?>