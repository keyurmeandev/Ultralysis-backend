awk -F ',' 'NR==FNR{c[$1$5]++;next};c[$1$5] > 0' $1 $2