# WordPress CSV Upload & Frontend Display Plugin

![Plugin Workflow](https://via.placeholder.com/800x400?text=CSV+Upload+Plugin+Screenshot)  
*Upload, manage, and display CSV data with powerful filtering capabilities*

## ✨ Features
- **Multiple CSV Instances**: Create separate datasets with unique IDs
- **Frontend Display**: Show data using shortcodes `[display_csv id="your_id"]`
- **Smart Filtering**:
  - Column-based dropdown filters
  - Default filter presets
  - Reset filters button
- **Admin Tools**:
  - Column selection (show/hide)
  - Filterable column configuration
  - CSV previews
  - Instance management
- **Responsive Design**: Mobile-friendly tables with hover effects
- **Security**: Input validation, nonce checks, and sanitization

## 📦 Installation
1. Upload plugin files to `/wp-content/plugins/`
2. Activate plugin in WordPress admin
3. Navigate to **CSV Upload** in admin sidebar

## 🖥 Admin Usage

### Uploading CSV
1. Go to *CSV Upload > Upload New CSV*
2. Enter unique Instance ID (e.g., `products`)
3. Select CSV file (≤2MB)
4. Toggle header row option
5. Click *Upload CSV*

### Configuring Columns
```text
1. Select columns to display
2. Mark columns as filterable
3. Set default filters (first item auto-applies)
