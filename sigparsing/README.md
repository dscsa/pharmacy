## Sig Parsing

The `SigParser` class takes care of the parsing of sigs using the [AWS Comprehend Medical API](https://docs.aws.amazon.com/comprehend/latest/dg/get-started-api-med.html).

Its function `parse($text, $drugname)` returns an array with the sig properties:
```
[
    "sig_qty" => How many pills/unit should the patient take
    "sig_days" => For how long should the prescription last (defaults to DAYS_STD)
    "sig_unit" => The units for the sig_qty
	"sig_conf_score" => Confidence score for the given result
]
```

### Benchmark queries

Given the `gp_rxs_single` table with columns:
- `sig_qty_per_day_(current|new)`: The value of the `sig_qty_per_day` returned by the parser (which should be `sig_qty / sig_days`)
- `sig_qty_per_day_actual`: The actual value of `sig_qty_per_day` verified.

To get the average error for those predictions which the parser got wrong (with a 0.1 tolerance), along with the percentage of how many are right:

```sql
SELECT 
	AVG(IF(error_def < 0.1, NULL, error_def)) as avg_offset_def,
	AVG(IF(error_new < 0.1, NULL, error_new)) as avg_offset_new,
	SUM(IF(error_def < 0.1, 0, 1)) as wrong_def,
	SUM(IF(error_new < 0.1, 0, 1)) as wrong_new,
	AVG(error_def < 0.1) as correct_perc_def,
	AVG(error_new < 0.1) as correct_perc_new,
	COUNT(error_def) as total_sigs 
FROM (
	SELECT
		ABS(sig_qty_per_day_default - sig_qty_per_day_actual) as error_def,
		ABS(sig_qty_per_day_new - sig_qty_per_day_actual) as error_new
	FROM
		gp_rxs_single
	WHERE 
		sig_qty_per_day_actual is not NULL
) AS benchmark;
```
