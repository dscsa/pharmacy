## Sig Parsing

The `SigParser` class takes care of the parsing of sigs using the [AWS Comprehend Medical API](https://docs.aws.amazon.com/comprehend/latest/dg/get-started-api-med.html).

Its function `parse($text, $drugname)` returns an array with the sig properties:
```
[
    "sig_qty" => How many pills/unit should the patient take
    "sig_days" => For how long should the prescription last (defaults to DAYS_STD)
    "sig_unit" => The units for the sig_qty
]
```

### Benchmark queries

Given the `gp_rxs_single` table with columns:
- `sig_qty_per_day_(current|new)`: The value of the `sig_qty_per_day` returned by the parser (which should be `sig_qty / sig_days`)
- `sig_qty_per_day_actual`: The actual value of `sig_qty_per_day` verified.

To get the percent of how many are right
```sql
SELECT 
	AVG(ABS(avg_act - avg_cur) = 0) as percent_cur,
	AVG(ABS(avg_act - avg_new) = 0) as percent_new,
	COUNT(avg_cur) 
FROM (
	SELECT
		avg(sig_qty_per_day_current) as avg_cur,
		avg(sig_qty_per_day_actual) as avg_act,
		avg(sig_qty_per_day_new) as avg_new
	FROM
		gp_rxs_single
	WHERE 
		sig_qty_per_day_actual is not NULL
	GROUP BY sig_initial, drug_name
) AS benchmark;
```

To get the average error for those predictions which the parser got wrong, along with how many it got wrong:

```sql
SELECT 
	AVG(NULLIF(ABS(avg_act - avg_def), 0)) as avg_offset_def,
	AVG(NULLIF(ABS(avg_act - avg_new), 0)) as avg_offset_new,
	COUNT(NULLIF(ABS(avg_act - avg_def), 0)) as wrong_def,
	COUNT(NULLIF(ABS(avg_act - avg_new), 0)) as wrong_new,
	COUNT(avg_def) as total_sigs 
FROM (
	SELECT
		avg(sig_qty_per_day_current) as avg_def,
		avg(sig_qty_per_day_actual) as avg_act,
		avg(sig_qty_per_day_new) as avg_new
	FROM
		gp_rxs_single
	WHERE 
		sig_qty_per_day_actual is not NULL
	GROUP BY sig_initial, drug_name
) AS benchmark;
```