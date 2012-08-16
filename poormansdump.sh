# Thanks to: http://www.mysqlperformanceblog.com/2008/11/07/poor-mans-query-logging/•
tcpdump -i lo -s 0 -l -w - dst port 3306 | strings | perl -e '
while(<>) {
      chomp;
      next if /^[^ ]+[ ]*$/;
      if(/^(SELECT|UPDATE|DELETE|INSERT|SET|COMMIT|ROLLBACK|CREATE|DROP|ALTER)/i) {
          if (defined $q) { print "$q\n"; }
          $q=$_;
      } else {
          $_ =~ s/^[ t]+//; $q.=" $_";
      }
}'

