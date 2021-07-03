




var a,b,c, i,j,k, n = 3;


a = [0,5,7,2,3,4,1,6];
b = a.slice(0, 2);// min,max
b.sort();
c = [];
d = [];

for (i = 2, j = a.length; i < j; ++i)
{
  if ((e = a[i]) > b[1])
  {
    c.unshift(b[1]);
    b[1] = e;
  }
  else if (e > b[0])
  {
    d.unshift(b[0]);
    b[0] = e;
  }
}
console.log(a);
console.log(b);
console.log(c);
console.log(d);









