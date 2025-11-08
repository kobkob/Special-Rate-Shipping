#include "Package.js"


/**
  * class Pouch
  * 
  */

Pouch = function ()
{
  this._init ();
}


/**
 * _init sets all Pouch attributes to their default value. Make sure to call this
 * method within your class constructor
 */
Pouch.prototype._init = function ()
{
  /**
   * Array of Package objects
   */
  this.m_packages = "";

  /**Aggregations: */

  /**Compositions: */

}

/**
 * Calculates the shipping price of an array of items
 * @param cart
    *      
 */
Pouch.prototype.calculate_shipping = function (cart)
{
  
}


/**
 * Returns the Pouch object as a json string
 */
Pouch.prototype.to_json = function ()
{
  
}


/**
 * Store the Pouch as a Data Type
 */
Pouch.prototype.savePouch = function ()
{
  
}



