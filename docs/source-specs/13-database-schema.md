# Database Schema

## Tabele principale
users
roles
permissions
stores
processors
customers
suppliers
processing_lots
purchase_lots
documents
inventory_transactions
audit_log

## processing_lots
id
lot_number
customer_id
status
gross_kg
shrinkage_pct
foundation_kg
store_id
created_by

## inventory_transactions
id
date
type
qty
store_id
reference_document
