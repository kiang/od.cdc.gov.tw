# COVID-19 Data Archive for Taiwan

This project archives COVID-19 case reports from Taiwan's CDC open data platform (https://data.gov.tw/) and converts them into formats suitable for visualization at https://kiang.github.io/covid19/.

## Data Source

The data is sourced from Taiwan CDC's open data platform:
- [地區年齡性別統計表-嚴重特殊傳染性肺炎-依個案研判日統計(以日為單位)](https://data.gov.tw/dataset/120711)

## Features

- Daily data fetching from Taiwan CDC
- Data transformation for visualization purposes
- Calculation of various statistics:
  - Confirmed cases by region
  - Gender distribution
  - Age distribution
  - 7-day averages
  - Increase rates

## Directory Structure

- `/raw/od2024`: Raw data files downloaded from CDC
- `/data/od2024`: Processed data files
  - `/confirmed`: Confirmed cases data
  - `/town`: Town-level statistics

## Usage

The main script `scripts/01_fetch_od.php` fetches the latest data and processes it into the required format.

```bash
php scripts/01_fetch_od.php
```

## Visualization

The processed data is used for visualization at https://kiang.github.io/covid19/, which provides interactive maps and charts of COVID-19 cases in Taiwan.

## License

MIT License, Copyright (c) 2024 Finjon Kiang. See the [LICENSE](LICENSE) file for details. 